<?php

namespace App\Modules\Contract\Http\Controllers;

use App\Modules\Contract\Http\Requests\StoreContractRequest;
use App\Modules\Contract\Http\Requests\UpdateContractRequest;
use App\Modules\Contract\Http\Resources\ContractResource;
use App\Modules\Contract\Models\Contract;
use App\Modules\Contract\Services\ContractService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends ApiController
{
    public function __construct(private readonly ContractService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        $contracts = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(ContractResource::collection($contracts));
    }

    public function show(Contract $contract): JsonResponse
    {
        $this->authorize('view', $contract);

        return $this->success(ContractResource::make($contract));
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $this->authorize('create', Contract::class);

        $contract = $this->service->create($request->validated());

        return $this->created(ContractResource::make($contract), 'Contrato criado com sucesso.');
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);

        $contract = $this->service->update($contract, $request->validated());

        return $this->success(ContractResource::make($contract), 'Contrato atualizado com sucesso.');
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $this->authorize('delete', $contract);

        $this->service->delete($contract);

        return $this->success(null, 'Contrato removido com sucesso.');
    }
}
