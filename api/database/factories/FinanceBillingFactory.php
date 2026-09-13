<?php

namespace Database\Factories;

use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceBilling>
 */
class FinanceBillingFactory extends Factory
{
    protected $model = FinanceBilling::class;

    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(1, 999999),
            'status' => BillingStatus::PENDING,
            'amount_cents' => 4990,
            'discount_cents' => 0,
            'fine_cents' => 0,
            'interest_cents' => 0,
            'due_at' => now()->addDays(10)->toDateString(),
            'description' => 'Mensalidade',
        ];
    }

    public function forSubscription(FinanceSubscription $subscription): static
    {
        return $this->state(fn () => [
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'client_id' => $subscription->client_id,
            'amount_cents' => $subscription->plan_price_cents ?? 4990,
        ]);
    }
}
