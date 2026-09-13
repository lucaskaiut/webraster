<?php

namespace App\Modules\Client\Http\Controllers;

use App\Modules\Client\Http\Requests\UpsertClientContractRequest;
use App\Modules\Client\Http\Resources\ClientContractResource;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Services\ClientContractService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class ClientContractController extends ApiController
{
    public function __construct(private readonly ClientContractService $contracts) {}

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $contract = $this->contracts->currentForClient($client);

        return $this->success($contract ? ClientContractResource::make($contract) : null);
    }

    public function upsert(UpsertClientContractRequest $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $contract = $this->contracts->assign($client, $request->validated());

        return $this->success(ClientContractResource::make($contract), 'Contrato vinculado com sucesso.');
    }
}
