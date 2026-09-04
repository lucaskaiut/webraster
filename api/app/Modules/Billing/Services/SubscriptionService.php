<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\SubscriptionEventType;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionCreated;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\PaymentGatewayResolver;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly SubscriptionEventService $events,
        private readonly PaymentGatewayResolver $gateways,
    ) {}

    public function createForTenant(Tenant $tenant, Plan|string $plan, ?string $paymentGateway = null): Subscription
    {
        if (is_string($plan)) {
            $plan = $this->plans->findActiveForSubscription($plan, $tenant);
        }

        if (filled($paymentGateway)) {
            $this->gateways->assertActive($paymentGateway);
        }

        if (Subscription::query()->withoutTenancy()->where('tenant_id', $tenant->getKey())->exists()) {
            throw ValidationException::withMessages([
                'subscription' => ['Esta empresa já possui uma assinatura.'],
            ]);
        }

        return DB::transaction(function () use ($tenant, $plan, $paymentGateway): Subscription {
            $now = CarbonImmutable::now();
            $hasTrial = $plan->hasTrial();

            $trialEndsAt = $hasTrial ? $now->addDays($plan->free_trial_days) : null;
            $nextBillingAt = $hasTrial ? $trialEndsAt : $now;
            $status = $hasTrial ? SubscriptionStatus::TRIALING : SubscriptionStatus::ACTIVE;

            $subscription = Subscription::query()->withoutTenancy()->create([
                'tenant_id' => $tenant->getKey(),
                'plan_id' => $plan->getKey(),
                'payment_gateway' => $paymentGateway,
                'status' => $status,
                'started_at' => $now,
                'trial_ends_at' => $trialEndsAt,
                'last_billed_at' => null,
                'next_billing_at' => $nextBillingAt,
                'cancelled_at' => null,
            ]);

            $this->events->record($subscription, SubscriptionEventType::SUBSCRIPTION_CREATED, [
                'plan_id' => $plan->uuid,
                'status' => $status->value,
                'payment_gateway' => $paymentGateway,
            ]);

            if ($hasTrial) {
                $this->events->record($subscription, SubscriptionEventType::TRIAL_STARTED, [
                    'trial_ends_at' => $trialEndsAt?->toIso8601String(),
                ]);
            }

            $subscription = $subscription->load('plan');

            SubscriptionCreated::dispatch($subscription);

            return $subscription;
        });
    }

    public function changePlan(
        Subscription $subscription,
        Plan|string $plan,
        ?string $paymentGateway = null,
    ): Subscription {
        if ($subscription->status === SubscriptionStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'subscription' => ['Não é possível alterar o plano de uma assinatura cancelada.'],
            ]);
        }

        $tenant = $subscription->tenant ?? Tenant::query()->findOrFail($subscription->tenant_id);

        if (is_string($plan)) {
            $plan = $this->plans->findActiveForSubscription($plan, $tenant);
        }

        if ($paymentGateway !== null) {
            $this->gateways->assertActive($paymentGateway);
            $subscription->payment_gateway = $paymentGateway;
        }

        $subscription->plan_id = $plan->getKey();
        $subscription->save();

        $this->events->record($subscription, SubscriptionEventType::SUBSCRIPTION_CREATED, [
            'action' => 'plan_changed',
            'plan_id' => $plan->uuid,
            'payment_gateway' => $subscription->payment_gateway,
        ]);

        return $subscription->refresh()->load('plan');
    }

    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::CANCELLED) {
            return $subscription;
        }

        $subscription->status = SubscriptionStatus::CANCELLED;
        $subscription->cancelled_at = now();
        $subscription->save();

        $this->events->record($subscription, SubscriptionEventType::SUBSCRIPTION_CANCELLED);

        return $subscription->refresh();
    }

    public function reactivate(Subscription $subscription): Subscription
    {
        if (! in_array($subscription->status, [
            SubscriptionStatus::SUSPENDED,
            SubscriptionStatus::PAST_DUE,
            SubscriptionStatus::CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'subscription' => ['A assinatura não está em um estado reativável.'],
            ]);
        }

        $subscription->loadMissing('plan');
        $now = CarbonImmutable::now();

        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->cancelled_at = null;
        $subscription->next_billing_at = $this->calculateNextBilling($subscription, $now);
        $subscription->save();

        $this->events->record($subscription, SubscriptionEventType::SUBSCRIPTION_REACTIVATED);

        return $subscription->refresh()->load('plan');
    }

    public function markPastDue(Subscription $subscription): Subscription
    {
        $subscription->status = SubscriptionStatus::PAST_DUE;
        $subscription->save();

        $this->events->record($subscription, SubscriptionEventType::PAYMENT_FAILED);

        return $subscription->refresh();
    }

    public function suspend(Subscription $subscription, array $payload = []): Subscription
    {
        $subscription->status = SubscriptionStatus::SUSPENDED;
        $subscription->save();

        $this->events->record($subscription, SubscriptionEventType::SUBSCRIPTION_SUSPENDED, $payload);

        return $subscription->refresh();
    }

    public function confirmPayment(Subscription $subscription): Subscription
    {
        $subscription->loadMissing('plan');
        $now = CarbonImmutable::now();

        if ($subscription->status === SubscriptionStatus::TRIALING) {
            $this->events->record($subscription, SubscriptionEventType::TRIAL_ENDED);
        }

        $subscription->last_billed_at = $now;
        $subscription->next_billing_at = $this->calculateNextBilling($subscription, $now);
        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->save();

        return $subscription->refresh()->load('plan');
    }

    public function calculateNextBilling(Subscription $subscription, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $subscription->loadMissing('plan');

        $base = $from ?? CarbonImmutable::instance(
            $subscription->last_billed_at ?? $subscription->started_at ?? now()
        );

        return $subscription->plan->calculateNextBillingFrom($base);
    }

    public function currentForTenant(Tenant $tenant): ?Subscription
    {
        return Subscription::query()
            ->withoutTenancy()
            ->with(['plan', 'events' => fn ($q) => $q->orderByDesc('created_at')->limit(50)])
            ->where('tenant_id', $tenant->getKey())
            ->first();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Subscription::query()
            ->with('plan')
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Subscription>
     */
    public function dueForBilling(): \Illuminate\Database\Eloquent\Collection
    {
        $leadDays = max(0, (int) config('billing.days_before_due', 0));
        $threshold = now()->addDays($leadDays);

        return Subscription::query()
            ->withoutTenancy()
            ->with(['plan', 'tenant'])
            ->whereIn('status', [
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::TRIALING->value,
            ])
            ->where('next_billing_at', '<=', $threshold)
            ->get();
    }
}
