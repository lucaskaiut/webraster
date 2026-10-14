<?php

namespace Tests\Feature\Dashboard;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MonitoringDashboardTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_metrics_classify_fleet_into_non_overlapping_offline_buckets(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        // Online: sinal recente.
        $online = Vehicle::factory()->forClient($client)->create(['plate' => 'ONL1N23']);
        Equipment::factory()->assignedTo($online)->create(['traccar_last_update' => now()->subMinutes(2)]);

        // Offline há 10h (até 24h).
        $offline24 = Vehicle::factory()->forClient($client)->create(['plate' => 'OF12423']);
        Equipment::factory()->assignedTo($offline24)->create(['traccar_last_update' => now()->subHours(10)]);

        // Offline há 30h (24h a 36h).
        $offline36 = Vehicle::factory()->forClient($client)->create(['plate' => 'OF23623']);
        Equipment::factory()->assignedTo($offline36)->create(['traccar_last_update' => now()->subHours(30)]);

        // Offline há 48h (+36h) e veículo sem nenhum sinal (+36h).
        $offlineLong = Vehicle::factory()->forClient($client)->create(['plate' => 'OF44823']);
        Equipment::factory()->assignedTo($offlineLong)->create(['traccar_last_update' => now()->subHours(48)]);

        $noSignal = Vehicle::factory()->forClient($client)->create(['plate' => 'NOS1G23']);
        Equipment::factory()->assignedTo($noSignal)->create(['traccar_last_update' => null]);

        // Veículo inativo conta apenas no total.
        $inactive = Vehicle::factory()->forClient($client)->create(['plate' => 'INA1T23', 'is_active' => false]);
        Equipment::factory()->assignedTo($inactive)->create();

        // Estoque: dois equipamentos ativos (um com linha) e um inativo.
        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '359633100001111',
            'line_number' => '11999998888',
        ]);
        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '359633100002222',
            'line_number' => null,
        ]);
        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '359633100003333',
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/dashboard/monitoring')
            ->assertOk()
            ->assertJsonPath('data.vehicles.total', 6)
            ->assertJsonPath('data.vehicles.online', 1)
            ->assertJsonPath('data.vehicles.offline_up_to_24h', 1)
            ->assertJsonPath('data.vehicles.offline_24h_to_36h', 1)
            ->assertJsonPath('data.vehicles.offline_over_36h', 2)
            ->assertJsonPath('data.equipments.active', 8)
            ->assertJsonPath('data.equipments.in_stock', 2)
            ->assertJsonPath('data.chips.in_stock', 1);
    }

    public function test_recent_position_keeps_vehicle_online_even_with_stale_traccar_signal(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create([
            'traccar_last_update' => now()->subHours(3),
        ]);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'equipment_id' => $equipment->getKey(),
            'recorded_at' => now()->subMinutes(1),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/dashboard/monitoring')
            ->assertOk()
            ->assertJsonPath('data.vehicles.online', 1)
            ->assertJsonPath('data.vehicles.offline_up_to_24h', 0);
    }

    public function test_requires_fleet_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createMember($tenant);
        $user->roles()->firstOrFail()->revokePermissions(
            Permission::TRACKING_READ,
            Permission::VEHICLE_READ,
            Permission::EQUIPMENT_READ,
        );

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/monitoring')->assertForbidden();
    }
}
