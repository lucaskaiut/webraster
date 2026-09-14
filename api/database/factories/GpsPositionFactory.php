<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GpsPosition>
 */
class GpsPositionFactory extends Factory
{
    protected $model = GpsPosition::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'client_id' => Client::factory(),
            'equipment_id' => null,
            'latitude' => fake()->latitude(-26, -25),
            'longitude' => fake()->longitude(-50, -49),
            'recorded_at' => now(),
            'speed' => fake()->randomFloat(2, 0, 120),
            'ignition' => fake()->boolean(),
            'battery' => fake()->randomFloat(2, 0, 100),
            'attributes' => [],
        ];
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $vehicle->tenant_id,
            'vehicle_id' => $vehicle->getKey(),
            'client_id' => $vehicle->client_id,
        ]);
    }
}
