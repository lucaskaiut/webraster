<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientContract;
use App\Modules\Contract\Models\Contract;
use Illuminate\Support\Facades\DB;

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

            $clientContract->fill([
                'contract_id' => $contract->getKey(),
                'valid_until' => $data['valid_until'] ?? null,
                'body' => $body,
            ]);
            $clientContract->save();

            return $clientContract->fresh('contract');
        });
    }
}
