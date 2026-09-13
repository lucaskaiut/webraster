<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Http\Requests\AssignFinanceSubscriptionRequest;
use App\Modules\Finance\Http\Requests\UpdateClientPlanRequest;
use App\Modules\Finance\Http\Requests\UpdateFinanceSubscriptionRequest;
use App\Modules\Finance\Http\Resources\FinanceSubscriptionResource;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\FinanceSubscriptionService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinanceSubscriptionController extends ApiController
{
    public function __construct(private readonly FinanceSubscriptionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceSubscription::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        if ($request->filled('client_id')) {
            $filters['client_id'] = Client::query()->where('uuid', $request->string('client_id')->toString())->value('id');
        }

        $subscriptions = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $filters,
        );

        return $this->paginated(FinanceSubscriptionResource::collection($subscriptions));
    }

    public function show(FinanceSubscription $financeSubscription): JsonResponse
    {
        $this->authorize('view', $financeSubscription);

        $financeSubscription->load(['client', 'plan']);

        return $this->success(FinanceSubscriptionResource::make($financeSubscription));
    }

    public function update(
        UpdateFinanceSubscriptionRequest $request,
        FinanceSubscription $financeSubscription,
    ): JsonResponse {
        $this->authorize('update', $financeSubscription);

        $subscription = $this->service->update($financeSubscription, $request->validated());

        return $this->success(FinanceSubscriptionResource::make($subscription), 'Assinatura atualizada.');
    }

    public function assign(AssignFinanceSubscriptionRequest $request): JsonResponse
    {
        $this->authorize('create', FinanceSubscription::class);

        $data = $request->validated();
        $client = Client::query()->findOrFail($data['client_id']);
        $plan = FinancePlan::query()->findOrFail($data['plan_id']);

        $subscription = $this->service->assignPlan($client, $plan, $data);

        return $this->success(FinanceSubscriptionResource::make($subscription), 'Plano atribuído à assinatura.');
    }

    public function updateClientPlan(UpdateClientPlanRequest $request, Client $client): JsonResponse
    {
        $this->authorize('create', FinanceSubscription::class);
        $this->authorize('update', $client);

        $data = $request->validated();
        $planId = $data['plan_id'] ?? null;

        if ($planId === null) {
            throw ValidationException::withMessages([
                'plan_id' => ['O plano é obrigatório para atribuir assinatura.'],
            ]);
        }

        $plan = FinancePlan::query()->findOrFail($planId);
        $subscription = $this->service->assignPlan($client, $plan, $data);

        return $this->success(FinanceSubscriptionResource::make($subscription), 'Plano do cliente atualizado.');
    }

    public function cancel(FinanceSubscription $financeSubscription): JsonResponse
    {
        $this->authorize('cancel', $financeSubscription);

        $subscription = $this->service->cancel($financeSubscription);

        return $this->success(FinanceSubscriptionResource::make($subscription), 'Assinatura cancelada.');
    }

    public function reactivate(FinanceSubscription $financeSubscription): JsonResponse
    {
        $this->authorize('reactivate', $financeSubscription);

        $subscription = $this->service->reactivate($financeSubscription);

        return $this->success(
            FinanceSubscriptionResource::make($subscription),
            'Assinatura reativada. Dispositivos liberados mesmo com inadimplência em aberto.',
        );
    }
}
