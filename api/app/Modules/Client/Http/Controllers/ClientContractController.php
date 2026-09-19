<?php

namespace App\Modules\Client\Http\Controllers;

use App\Modules\Client\Enums\ContractSignatureStatus;
use App\Modules\Client\Http\Requests\SignClientContractRequest;
use App\Modules\Client\Http\Requests\UpdateClientContractSignatureRequest;
use App\Modules\Client\Http\Requests\UpsertClientContractRequest;
use App\Modules\Client\Http\Resources\ClientContractResource;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Services\ClientContractService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function updateSignature(UpdateClientContractSignatureRequest $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $status = ContractSignatureStatus::from($request->validated('signature_status'));

        $contract = $this->contracts->updateSignature($client, $status);

        abort_if($contract === null, 404, 'Nenhum contrato vinculado a este cliente.');

        return $this->success(
            ClientContractResource::make($contract),
            $status->isSigned() ? 'Contrato marcado como assinado.' : 'Contrato marcado como pendente.',
        );
    }

    public function sign(SignClientContractRequest $request, Client $client): JsonResponse
    {
        $this->authorize('signContract', $client);

        $contract = $this->contracts->sign(
            $client,
            (int) $request->validated('contract_id'),
            $request->file('image'),
        );

        return $this->success(ClientContractResource::make($contract), 'Contrato assinado com sucesso.');
    }

    public function portal(Request $request): JsonResponse
    {
        $client = $request->user()?->client;

        abort_if($client === null, 403, 'Acesso restrito a usuários do portal do cliente.');

        $this->authorize('signContract', $client);

        $contract = $this->contracts->currentForClient($client);

        return $this->success($contract ? ClientContractResource::make($contract) : null);
    }
}
