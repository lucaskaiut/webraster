<?php

namespace Database\Factories;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleImage>
 */
class VehicleImageFactory extends Factory
{
    protected $model = VehicleImage::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'path' => 'uploads/'.fake()->uuid().'.jpg',
            'sort_order' => 0,
        ];
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $vehicle->tenant_id,
            'vehicle_id' => $vehicle->getKey(),
        ]);
    }
}
