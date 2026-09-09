<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Enums\BillingPeriodicity;
use App\Modules\Finance\Enums\ContractStatus;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceContract>
 */
class FinanceContractFactory extends Factory
{
    protected $model = FinanceContract::class;

    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(1, 999999),
            'status' => ContractStatus::ACTIVE,
            'starts_at' => now()->toDateString(),
            'periodicity' => BillingPeriodicity::MONTHLY,
            'due_day' => 10,
            'amount_cents' => 4990,
            'discount_cents' => 0,
            'fine_percent' => 2,
            'interest_percent' => 1,
            'device_quantity' => 1,
            'auto_renew' => true,
            'block_on_overdue' => true,
            'block_after_days' => 5,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn () => [
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->getKey(),
        ]);
    }

    public function withPlan(FinancePlan $plan): static
    {
        return $this->state(fn () => [
            'plan_id' => $plan->getKey(),
            'amount_cents' => $plan->amount_cents,
            'periodicity' => $plan->periodicity,
        ]);
    }
}
