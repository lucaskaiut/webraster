<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Enums\ContractStatus;
use App\Modules\Finance\Http\Requests\ChangeFinanceContractStatusRequest;
use App\Modules\Finance\Http\Requests\StoreFinanceContractRequest;
use App\Modules\Finance\Http\Requests\UpdateFinanceContractRequest;
use App\Modules\Finance\Http\Resources\FinanceContractResource;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Services\FinanceContractService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceContractController extends ApiController
{
    public function __construct(private readonly FinanceContractService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceContract::class);

        $contracts = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $this->filters($request),
        );

        return $this->paginated(FinanceContractResource::collection($contracts));
    }

    public function show(FinanceContract $financeContract): JsonResponse
    {
        $this->authorize('view', $financeContract);

        $financeContract->load(['client', 'plan', 'subscription', 'creator']);

        return $this->success(FinanceContractResource::make($financeContract));
    }

    public function store(StoreFinanceContractRequest $request): JsonResponse
    {
        $this->authorize('create', FinanceContract::class);

        $contract = $this->service->create($request->validated(), $request->user());

        return $this->created(FinanceContractResource::make($contract), 'Contrato criado.');
    }

    public function update(UpdateFinanceContractRequest $request, FinanceContract $financeContract): JsonResponse
    {
        $this->authorize('update', $financeContract);

        $contract = $this->service->update($financeContract, $request->validated());

        return $this->success(FinanceContractResource::make($contract), 'Contrato atualizado.');
    }

    public function destroy(FinanceContract $financeContract): JsonResponse
    {
        $this->authorize('delete', $financeContract);

        $this->service->delete($financeContract);

        return $this->success(null, 'Contrato removido.');
    }

    public function changeStatus(ChangeFinanceContractStatusRequest $request, FinanceContract $financeContract): JsonResponse
    {
        $this->authorize('changeStatus', $financeContract);

        $contract = $this->service->changeStatus(
            $financeContract,
            ContractStatus::from((string) $request->validated('status')),
        );

        return $this->success(FinanceContractResource::make($contract), 'Status atualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        if ($request->filled('client_id')) {
            $filters['client_id'] = Client::query()->where('uuid', $request->string('client_id')->toString())->value('id');
        }

        if ($request->filled('plan_id')) {
            $filters['plan_id'] = FinancePlan::query()->where('uuid', $request->string('plan_id')->toString())->value('id');
        }

        return $filters;
    }
}
