<?php

namespace Database\Factories;

use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceCommandLog>
 */
class DeviceCommandLogFactory extends Factory
{
    protected $model = DeviceCommandLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => null,
            'equipment_id' => Equipment::factory(),
            'user_id' => null,
            'command_type' => 'engineStop',
            'payload' => ['type' => 'engineStop'],
            'status' => DeviceCommandStatus::SUCCESS,
            'requested_at' => now(),
            'executed_at' => now(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $vehicle->tenant_id,
            'vehicle_id' => $vehicle->getKey(),
        ]);
    }

    public function forEquipment(Equipment $equipment): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $equipment->tenant_id,
            'equipment_id' => $equipment->getKey(),
            'vehicle_id' => $equipment->vehicle_id,
        ]);
    }

    public function by(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->getKey(),
        ]);
    }
}
