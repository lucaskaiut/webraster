<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Http\Requests\ChangePlanRequest;
use App\Modules\Billing\Http\Requests\StoreSubscriptionRequest;
use App\Modules\Billing\Http\Resources\SubscriptionResource;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Support\PaymentGatewayResolver;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends ApiController
{
    public function __construct(
        private readonly SubscriptionService $service,
        private readonly BillingService $billing,
        private readonly PaymentGatewayResolver $gateways,
    ) {}

    public function gateways(): JsonResponse
    {
        return $this->success($this->gateways->catalog());
    }

    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $subscription = $this->service->currentForTenant(TenantContext::tenant());

        if ($subscription === null) {
            return $this->success(null, 'Nenhuma assinatura encontrada.');
        }

        $this->authorize('view', $subscription);

        return $this->success(SubscriptionResource::make($subscription));
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        $subscription = $this->service->createForTenant(
            TenantContext::tenant(),
            $request->validated('plan_id'),
            $request->validated('payment_gateway'),
        );

        $subscription->loadMissing('plan');

        if ($subscription->plan?->requiresImmediatePayment()) {
            $now = CarbonImmutable::now();
            $windowDays = max(1, (int) config('billing.payment_window_days', 3));

            $this->billing->createLocalInvoice(
                $subscription->fresh(['plan', 'tenant']),
                dueDate: $now,
                expiresAt: $now->addDays($windowDays),
            );
        }

        return $this->created(
            SubscriptionResource::make($subscription->fresh(['plan', 'events'])),
            'Assinatura criada com sucesso.',
        );
    }

    public function changePlan(ChangePlanRequest $request): JsonResponse
    {
        $subscription = $this->requireCurrentSubscription();
        $this->authorize('update', $subscription);

        return $this->success(
            SubscriptionResource::make(
                $this->service->changePlan(
                    $subscription,
                    $request->validated('plan_id'),
                    $request->validated('payment_gateway'),
                )
            ),
            'Plano alterado com sucesso.',
        );
    }

    public function cancel(): JsonResponse
    {
        $subscription = $this->requireCurrentSubscription();
        $this->authorize('update', $subscription);

        return $this->success(
            SubscriptionResource::make($this->service->cancel($subscription)),
            'Assinatura cancelada.',
        );
    }

    public function reactivate(): JsonResponse
    {
        $subscription = $this->requireCurrentSubscription();
        $this->authorize('update', $subscription);

        return $this->success(
            SubscriptionResource::make($this->service->reactivate($subscription)),
            'Assinatura reativada.',
        );
    }

    private function requireCurrentSubscription(): Subscription
    {
        $subscription = $this->service->currentForTenant(TenantContext::tenant());

        abort_if($subscription === null, 404, 'Assinatura não encontrada.');

        return $subscription;
    }
}
