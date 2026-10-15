<?php

namespace App\Modules\Client\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Client\Enums\ContractSignatureStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientContract;
use App\Modules\Contract\Models\Contract;
use App\Modules\ContractEvidence\Enums\SignatureMethod;
use App\Modules\ContractEvidence\Services\ContractEvidenceRecorder;
use App\Modules\ContractEvidence\Services\ContractSigningService;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ClientContractService
{
    public function __construct(
        private readonly ContractBodyRenderer $renderer,
        private readonly AuditLogService $audit,
        private readonly ContractSigningService $signing,
        private readonly ContractEvidenceRecorder $evidence,
    ) {}

    public function currentForClient(Client $client): ?ClientContract
    {
        return ClientContract::query()
            ->with('contract')
            ->where('client_id', $client->getKey())
            ->first();
    }

    /**
     * Vincula (ou revincula) o contrato. Um contrato já assinado com o mesmo
     * template mantém o texto assinado congelado; trocar de template ou
     * alterar o texto de um contrato pendente gera nova versão do documento
     * e invalida as sessões de assinatura abertas.
     *
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
            $isSigned = $clientContract->signature_status === ContractSignatureStatus::SIGNED;

            if (! $contractChanged && $isSigned) {
                return $clientContract->fresh('contract');
            }

            $context = $this->context();
            $actor = $this->actor();
            $isNew = ! $clientContract->exists;
            $previousVersion = (int) ($clientContract->document_version ?? 1);
            $bodyChanged = (string) $clientContract->body !== $body;

            if ($contractChanged && ! $isNew) {
                $this->evidence->supersedeCurrent($clientContract, 'contract_replaced', $context, $actor);
                $this->clearSignatureFields($clientContract);
            }

            $clientContract->fill([
                'contract_id' => $contract->getKey(),
                'body' => $body,
            ]);

            if (! $isNew && ($contractChanged || $bodyChanged)) {
                $clientContract->document_version = $previousVersion + 1;
            }

            $clientContract->save();

            if (! $isNew && ($contractChanged || $bodyChanged)) {
                $this->signing->revokeOpenSessions($clientContract, 'document_changed', $context, $actor?->getKey());
                $this->evidence->documentVersionChanged($clientContract, $previousVersion, $context, $actor);
            }

            return $clientContract->fresh('contract');
        });
    }

    /**
     * Endpoint antigo de assinatura do app; delega ao fluxo com evidências.
     *
     * @param  array{signer_name: string, signer_cpf: string, signer_birth_date: string}  $signer
     */
    public function sign(User $user, Client $client, int $contractId, UploadedFile $image, array $signer): ClientContract
    {
        $this->signing->signLegacy($user, $client, $contractId, $image, $signer, $this->context());

        return $this->currentForClient($client);
    }

    /**
     * Alteração manual do status pela equipe. "Assinado" gera um registro de
     * atestado manual (distinto de uma assinatura do cliente); "pendente"
     * marca a assinatura vigente como substituída, sem apagar evidências.
     */
    public function updateSignature(Client $client, ContractSignatureStatus $status): ?ClientContract
    {
        $clientContract = $this->currentForClient($client);

        if ($clientContract === null) {
            return null;
        }

        $previousStatus = $clientContract->signature_status?->value;

        if ($previousStatus === $status->value) {
            return $clientContract->fresh('contract');
        }

        return DB::transaction(function () use ($clientContract, $status, $previousStatus) {
            $context = $this->context();
            $actor = $this->actor();

            if ($status->isSigned()) {
                $signature = $this->evidence->recordStaffSignature(
                    $clientContract,
                    SignatureMethod::MANUAL_ATTESTATION,
                    $actor,
                    $context,
                );

                $clientContract->fill([
                    'signature_status' => $status,
                    'signed_at' => now(),
                    'document_hash' => $signature->presented_document_sha256,
                    'signed_ip' => $context->ip,
                    'signed_user_agent' => $context->userAgent,
                ]);
                $clientContract->current_signature_id = $signature->getKey();
            } else {
                $this->evidence->supersedeCurrent($clientContract, 'marked_pending', $context, $actor);
                $clientContract->signature_status = $status;
                $clientContract->signed_at = null;
                $this->clearSignatureFields($clientContract);
            }

            $clientContract->save();

            $this->recordAudit($clientContract, AuditAction::ContractSignatureUpdated, [
                'previous_status' => $previousStatus,
                'signature_status' => $status->value,
                'document_hash' => $clientContract->document_hash,
            ]);

            return $clientContract->fresh('contract');
        });
    }

    /**
     * Importa um contrato já assinado (PDF ou imagem) e marca o vínculo como
     * assinado, atendendo clientes que chegam de outra plataforma e não
     * precisam passar pelo fluxo de assinatura. O arquivo é preservado no
     * armazenamento privado de evidências.
     */
    public function uploadDocument(Client $client, UploadedFile $file): ClientContract
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        return DB::transaction(function () use ($client, $file, $bytes, $extension) {
            $clientContract = ClientContract::withTrashed()
                ->where('client_id', $client->getKey())
                ->first();

            if ($clientContract === null) {
                $clientContract = new ClientContract([
                    'client_id' => $client->getKey(),
                    'body' => '',
                ]);
                $clientContract->save();
            }

            if ($clientContract->trashed()) {
                $clientContract->restore();
            }

            $context = $this->context();

            $signature = $this->evidence->recordStaffSignature(
                $clientContract,
                SignatureMethod::UPLOADED_DOCUMENT,
                $this->actor(),
                $context,
                [
                    'bytes' => $bytes,
                    'media_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                    'name' => $file->getClientOriginalName(),
                    'extension' => preg_match('/^[a-z0-9]{1,5}$/', $extension) === 1 ? $extension : 'bin',
                ],
            );

            $clientContract->fill([
                'signature_status' => ContractSignatureStatus::SIGNED,
                'signed_at' => now(),
                'document_path' => $signature->document_path,
                'document_name' => $file->getClientOriginalName(),
                'document_hash' => $signature->document_sha256,
                'signature_hash' => null,
                'signed_ip' => $context->ip,
                'signed_user_agent' => $context->userAgent,
            ]);
            $clientContract->current_signature_id = $signature->getKey();
            $clientContract->save();

            $this->recordAudit($clientContract, AuditAction::ContractDocumentUploaded, [
                'document_name' => $clientContract->document_name,
                'document_hash' => $clientContract->document_hash,
                'signature_uuid' => $signature->uuid,
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

                $this->evidence->contractWaived($clientContract, $this->context(), $this->actor());
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
            && $clientContract->current_signature_id === null
            && blank($clientContract->body);

        if ($isPlaceholder) {
            $clientContract->delete();
        }
    }

    /**
     * Limpa apenas o estado atual do vínculo; os artefatos e registros de
     * evidência permanecem no histórico (contract_signatures).
     */
    private function clearSignatureFields(ClientContract $clientContract): void
    {
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
        $clientContract->current_signature_id = null;
    }

    private function context(): RequestEvidence
    {
        return RequestEvidence::fromRequest(request());
    }

    private function actor(): ?User
    {
        $user = request()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAudit(ClientContract $contract, AuditAction $action, array $details = []): void
    {
        $user = $this->actor();

        if ($user === null) {
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
