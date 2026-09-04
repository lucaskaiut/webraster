<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Http\Requests\StorePlanRequest;
use App\Modules\Billing\Http\Requests\UpdatePlanRequest;
use App\Modules\Billing\Http\Resources\PlanResource;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\PlanService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends ApiController
{
    public function __construct(
        private readonly PlanService $service,
    ) {}

    public function catalog(): JsonResponse
    {
        return $this->success(
            PlanResource::collection($this->service->publicCatalog()),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Plan::class);

        return $this->paginated(
            PlanResource::collection(
                $this->service->paginate((int) $request->integer('per_page', 15))
            ),
        );
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $this->authorize('create', Plan::class);

        return $this->created(
            PlanResource::make($this->service->create($request->validated())),
            'Plano criado com sucesso.',
        );
    }

    public function show(Plan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return $this->success(PlanResource::make($plan));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        return $this->success(
            PlanResource::make($this->service->update($plan, $request->validated())),
            'Plano atualizado com sucesso.',
        );
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        return $this->success(
            PlanResource::make($this->service->deactivate($plan)),
            'Plano inativado com sucesso.',
        );
    }
}
