<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Shared\Subscription\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceSubscription>
 */
class FinanceSubscriptionFactory extends Factory
{
    protected $model = FinanceSubscription::class;

    public function definition(): array
    {
        return [
            'status' => SubscriptionStatus::ACTIVE,
            'plan_name' => 'Plano Teste',
            'plan_price_cents' => 4990,
            'plan_periodicity' => BillingPeriodicity::MONTHLY,
            'due_day' => 10,
            'block_on_overdue' => true,
            'block_after_days' => 5,
            'next_billing_at' => now()->addMonth()->toDateString(),
            'started_at' => now(),
        ];
    }

    public function forClient(Client $client, ?FinancePlan $plan = null): static
    {
        return $this->state(function () use ($client, $plan) {
            $resolvedPlan = $plan;

            return [
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->getKey(),
                'plan_id' => $resolvedPlan?->getKey(),
                'plan_name' => $resolvedPlan?->name ?? 'Plano Teste',
                'plan_price_cents' => $resolvedPlan?->amount_cents ?? 4990,
                'plan_periodicity' => $resolvedPlan?->periodicity ?? BillingPeriodicity::MONTHLY,
            ];
        });
    }
}
