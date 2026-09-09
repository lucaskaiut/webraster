<?php

namespace Database\Factories;

use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinanceReceivable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceReceivable>
 */
class FinanceReceivableFactory extends Factory
{
    protected $model = FinanceReceivable::class;

    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(1, 999999),
            'status' => ReceivableStatus::PENDING,
            'amount_cents' => 4990,
            'discount_cents' => 0,
            'fine_cents' => 0,
            'interest_cents' => 0,
            'due_at' => now()->addDays(10)->toDateString(),
            'description' => 'Mensalidade',
        ];
    }

    public function forContract(FinanceContract $contract): static
    {
        return $this->state(fn () => [
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->getKey(),
            'client_id' => $contract->client_id,
            'subscription_id' => $contract->subscription?->getKey(),
            'amount_cents' => $contract->netAmountCents(),
        ]);
    }
}
