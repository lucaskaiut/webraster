<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Enums\ContractSignatureStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientContract;
use App\Modules\Contract\Models\Contract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientContractService
{
    public function __construct(private readonly ContractBodyRenderer $renderer) {}

    public function currentForClient(Client $client): ?ClientContract
    {
        return ClientContract::query()
            ->with('contract')
            ->where('client_id', $client->getKey())
            ->first();
    }

    /**
     * @param  array{contract_id: int, valid_until: ?string}  $data
     */
    public function assign(Client $client, array $data): ClientContract
    {
        $contract = Contract::query()->findOrFail((int) $data['contract_id']);

        $order = $client->order()->with('items.service', 'items.vehicles')->first();

        $body = $this->renderer->render($contract, $client, $order);

        return DB::transaction(function () use ($client, $contract, $data, $body) {
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
                'valid_until' => $data['valid_until'] ?? null,
                'body' => $body,
            ]);

            if ($contractChanged) {
                $clientContract->signature_status = ContractSignatureStatus::PENDING;
                $clientContract->signed_at = null;
                $clientContract->signature_path = null;
            }

            $clientContract->save();

            return $clientContract->fresh('contract');
        });
    }

    public function sign(Client $client, int $contractId, UploadedFile $image): ClientContract
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
        ]);
        $clientContract->save();

        return $clientContract->fresh('contract');
    }

    public function updateSignature(Client $client, ContractSignatureStatus $status): ?ClientContract
    {
        $clientContract = $this->currentForClient($client);

        if ($clientContract === null) {
            return null;
        }

        $clientContract->fill([
            'signature_status' => $status,
            'signed_at' => $status->isSigned() ? now() : null,
        ]);

        if (! $status->isSigned()) {
            $clientContract->signature_path = null;
        }

        $clientContract->save();

        return $clientContract->fresh('contract');
    }
}
