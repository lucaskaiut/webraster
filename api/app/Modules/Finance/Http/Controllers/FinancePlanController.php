<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Http\Requests\StoreFinancePlanRequest;
use App\Modules\Finance\Http\Requests\UpdateFinancePlanRequest;
use App\Modules\Finance\Http\Resources\FinancePlanResource;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Services\FinancePlanService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancePlanController extends ApiController
{
    public function __construct(private readonly FinancePlanService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinancePlan::class);

        $plans = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            [
                'search' => $request->string('search')->toString() ?: null,
                'is_active' => $request->has('is_active') ? $request->input('is_active') : null,
            ],
        );

        return $this->paginated(FinancePlanResource::collection($plans));
    }

    public function show(FinancePlan $financePlan): JsonResponse
    {
        $this->authorize('view', $financePlan);

        return $this->success(FinancePlanResource::make($financePlan));
    }

    public function store(StoreFinancePlanRequest $request): JsonResponse
    {
        $this->authorize('create', FinancePlan::class);

        $plan = $this->service->create($request->validated());

        return $this->created(FinancePlanResource::make($plan), 'Plano criado.');
    }

    public function update(UpdateFinancePlanRequest $request, FinancePlan $financePlan): JsonResponse
    {
        $this->authorize('update', $financePlan);

        $plan = $this->service->update($financePlan, $request->validated());

        return $this->success(FinancePlanResource::make($plan), 'Plano atualizado.');
    }

    public function destroy(FinancePlan $financePlan): JsonResponse
    {
        $this->authorize('delete', $financePlan);

        $this->service->delete($financePlan);

        return $this->success(null, 'Plano removido.');
    }
}
