<?php

namespace Database\Factories;

use App\Modules\Finance\Enums\BillingPeriodicity;
use App\Modules\Finance\Enums\SubscriptionStatus;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinanceSubscription;
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
            'periodicity' => BillingPeriodicity::MONTHLY,
            'next_billing_at' => now()->addMonth()->toDateString(),
        ];
    }

    public function forContract(FinanceContract $contract): static
    {
        return $this->state(fn () => [
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->getKey(),
            'client_id' => $contract->client_id,
            'periodicity' => $contract->periodicity,
        ]);
    }
}
