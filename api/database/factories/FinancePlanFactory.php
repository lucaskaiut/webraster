<?php

namespace Database\Factories;

use App\Modules\Finance\Enums\PlanPricingType;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancePlan>
 */
class FinancePlanFactory extends Factory
{
    protected $model = FinancePlan::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Plano '.$this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'amount_cents' => 4990,
            'pricing_type' => PlanPricingType::FIXED,
            'periodicity' => BillingPeriodicity::MONTHLY,
            'device_limit' => 5,
            'is_active' => true,
        ];
    }

    public function perVehicle(): static
    {
        return $this->state(fn () => ['pricing_type' => PlanPricingType::PER_VEHICLE]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->getKey()]);
    }
}
