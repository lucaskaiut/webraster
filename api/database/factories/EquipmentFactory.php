<?php

namespace Database\Factories;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => null,
            'imei' => fake()->unique()->numerify('###############'),
            'model' => fake()->randomElement(['GT06N', 'TK103', 'FMB920', 'GV55']),
            'iccid' => fake()->numerify('####################'),
            'carrier' => fake()->randomElement(['Vivo', 'Claro', 'TIM', 'Oi']),
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function assignedTo(Vehicle $vehicle): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $vehicle->tenant_id,
            'vehicle_id' => $vehicle->getKey(),
        ]);
    }
}
