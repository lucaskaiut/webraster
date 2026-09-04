<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\RecurrenceUnit;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Plano '.fake()->unique()->word(),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 29.90, 499.90),
            'recurrence_value' => 30,
            'recurrence_unit' => RecurrenceUnit::DAYS,
            'free_trial_days' => 7,
            'active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function withoutTrial(): static
    {
        return $this->state(fn (): array => [
            'free_trial_days' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
