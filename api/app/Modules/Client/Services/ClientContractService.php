<?php

namespace App\Modules\Client\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Client\Enums\ContractSignatureStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientContract;
use App\Modules\Contract\Models\Contract;
use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientContractService
{
    public function __construct(
        private readonly ContractBodyRenderer $renderer,
        private readonly AuditLogService $audit,
    ) {}

    public function currentForClient(Client $client): ?ClientContract
    {
        return ClientContract::query()
            ->with('contract')
            ->where('client_id', $client->getKey())
            ->first();
    }

    /**
     * @param  array{contract_id: int}  $data
     */
    public function assign(Client $client, array $data): ClientContract
    {
        $contract = Contract::query()->findOrFail((int) $data['contract_id']);

        $order = $client->order()->with('items.service', 'items.vehicles')->first();

        $body = $this->renderer->render($contract, $client, $order);

        return DB::transaction(function () use ($client, $contract, $body) {
            $clientContract = ClientContract::withTrashed()
                ->where('client_id', $client->getKey())
                ->first();

            if ($clientContract === null) {
                $clientContract = new ClientContract;
                $clientContract->client_id = $client->getKey();
            }

            if ($clientContract->trashed()) {
                $clientContract->restore();
            }

            $contractChanged = (int) $clientContract->contract_id !== (int) $contract->getKey();

            $clientContract->fill([
                'contract_id' => $contract->getKey(),
                'body' => $body,
            ]);

            if ($contractChanged) {
                $clientContract->signature_status = ContractSignatureStatus::PENDING;
                $clientContract->signed_at = null;
                $clientContract->signature_path = null;
                $clientContract->document_path = null;
                $clientContract->document_name = null;
                $clientContract->document_hash = null;
                $clientContract->signature_hash = null;
                $clientContract->signed_ip = null;
                $clientContract->signed_user_agent = null;
                $clientContract->signer_name = null;
                $clientContract->signer_cpf = null;
                $clientContract->signer_birth_date = null;
            }

            $clientContract->save();

            return $clientContract->fresh('contract');
        });
    }

    /**
     * @param  array{signer_name: string, signer_cpf: string, signer_birth_date: string}  $signer
     */
    public function sign(Client $client, int $contractId, UploadedFile $image, array $signer): ClientContract
    {
        $clientContract = $this->currentForClient($client);

        if ($clientContract === null) {
            throw ValidationException::withMessages([
                'contract_id' => ['Nenhum contrato vinculado a este cliente.'],
            ]);
        }

        if ((int) $clientContract->contract_id !== $contractId) {
            throw ValidationException::withMessages([
                'contract_id' => ['O contrato informado não é o contrato vigente deste cliente.'],
            ]);
        }

        $path = $image->store('signatures', 'public');

        $clientContract->fill([
            'signature_status' => ContractSignatureStatus::SIGNED,
            'signed_at' => now(),
            'signature_path' => $path,
            'document_hash' => hash('sha256', (string) $clientContract->body),
            'signature_hash' => hash_file('sha256', $image->getRealPath()) ?: null,
            'signed_ip' => request()->ip(),
            'signed_user_agent' => Str::limit((string) request()->userAgent(), 512, ''),
            'signer_name' => $signer['signer_name'],
            'signer_cpf' => $signer['signer_cpf'],
            'signer_birth_date' => $signer['signer_birth_date'],
        ]);
        $clientContract->save();

        $this->recordAudit($clientContract, AuditAction::ContractSigned, [
            'signature_status' => ContractSignatureStatus::SIGNED->value,
            'document_hash' => $clientContract->document_hash,
            'signature_hash' => $clientContract->signature_hash,
            'signer_cpf' => $clientContract->signer_cpf,
        ]);

        return $clientContract->fresh('contract');
    }

    public function updateSignature(Client $client, ContractSignatureStatus $status): ?ClientContract
    {
        $clientContract = $this->currentForClient($client);

        if ($clientContract === null) {
            return null;
        }

        $previousStatus = $clientContract->signature_status?->value;

        $clientContract->fill([
            'signature_status' => $status,
            'signed_at' => $status->isSigned() ? now() : null,
        ]);

        if ($status->isSigned()) {
            $clientContract->fill([
                'document_hash' => $clientContract->document_hash
                    ?: hash('sha256', (string) $clientContract->body),
                'signed_ip' => request()->ip(),
                'signed_user_agent' => Str::limit((string) request()->userAgent(), 512, ''),
            ]);
        } else {
            $clientContract->signature_path = null;
            $clientContract->document_path = null;
            $clientContract->document_name = null;
            $clientContract->document_hash = null;
            $clientContract->signature_hash = null;
            $clientContract->signed_ip = null;
            $clientContract->signed_user_agent = null;
            $clientContract->signer_name = null;
            $clientContract->signer_cpf = null;
            $clientContract->signer_birth_date = null;
        }

        $clientContract->save();

        $this->recordAudit($clientContract, AuditAction::ContractSignatureUpdated, [
            'previous_status' => $previousStatus,
            'signature_status' => $status->value,
            'document_hash' => $clientContract->document_hash,
        ]);

        return $clientContract->fresh('contract');
    }

    /**
     * Importa um contrato já assinado (PDF ou imagem) e marca o vínculo como
     * assinado, atendendo clientes que chegam de outra plataforma e não
     * precisam passar pelo fluxo de assinatura.
     */
    public function uploadDocument(Client $client, UploadedFile $file): ClientContract
    {
        $path = $file->store('contracts', 'public');

        return DB::transaction(function () use ($client, $file, $path) {
            $clientContract = ClientContract::withTrashed()
                ->where('client_id', $client->getKey())
                ->first();

            if ($clientContract === null) {
                $clientContract = new ClientContract([
                    'client_id' => $client->getKey(),
                    'body' => '',
                ]);
            }

            if ($clientContract->trashed()) {
                $clientContract->restore();
            }

            $clientContract->fill([
                'signature_status' => ContractSignatureStatus::SIGNED,
                'signed_at' => now(),
                'document_path' => $path,
                'document_name' => $file->getClientOriginalName(),
                'document_hash' => hash_file('sha256', $file->getRealPath()) ?: null,
                'signature_hash' => null,
                'signed_ip' => request()->ip(),
                'signed_user_agent' => Str::limit((string) request()->userAgent(), 512, ''),
            ]);
            $clientContract->save();

            $this->recordAudit($clientContract, AuditAction::ContractDocumentUploaded, [
                'document_name' => $clientContract->document_name,
                'document_hash' => $clientContract->document_hash,
            ]);

            return $clientContract->fresh('contract');
        });
    }

    /**
     * Dispensa o contrato: garante um vínculo assinado (sem template e sem
     * documento) para que o aplicativo considere o acesso do cliente liberado.
     */
    public function markWithoutContract(Client $client): ClientContract
    {
        return DB::transaction(function () use ($client) {
            $clientContract = ClientContract::withTrashed()
                ->where('client_id', $client->getKey())
                ->first();

            if ($clientContract === null) {
                $clientContract = new ClientContract([
                    'client_id' => $client->getKey(),
                    'body' => '',
                ]);
            }

            if ($clientContract->trashed()) {
                $clientContract->restore();
            }

            if ($clientContract->signature_status !== ContractSignatureStatus::SIGNED) {
                $clientContract->fill([
                    'signature_status' => ContractSignatureStatus::SIGNED,
                    'signed_at' => now(),
                ]);
                $clientContract->save();
            }

            return $clientContract->fresh('contract');
        });
    }

    /**
     * Volta a exigir contrato: remove o vínculo criado apenas para dispensar
     * a assinatura. Contratos reais (com template, arquivo ou assinatura)
     * são preservados.
     */
    public function clearWithoutContract(Client $client): void
    {
        $clientContract = $this->currentForClient($client);

        if ($clientContract === null) {
            return;
        }

        $isPlaceholder = $clientContract->contract_id === null
            && $clientContract->document_path === null
            && $clientContract->signature_path === null
            && blank($clientContract->body);

        if ($isPlaceholder) {
            $clientContract->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAudit(ClientContract $contract, AuditAction $action, array $details = []): void
    {
        $user = request()->user();

        if (! $user instanceof User) {
            return;
        }

        $this->audit->recordEntity(
            $user,
            $action,
            'client_contract',
            (string) $contract->uuid,
            $details,
        );
    }
}
