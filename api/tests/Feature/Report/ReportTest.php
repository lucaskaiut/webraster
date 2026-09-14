<?php

namespace Tests\Feature\Report;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_commands_report_lists_rows_with_filters(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23', 'model' => 'Gol']);
        $equipment = Equipment::factory()->assignedTo($vehicle)->create(['imei' => '123456789012345']);
        $admin = $this->createAdmin($tenant);

        DeviceCommandLog::factory()->forTenant($tenant)->forEquipment($equipment)->by($admin)->create([
            'command_type' => 'engineStop',
            'status' => DeviceCommandStatus::SUCCESS,
            'requested_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/reports/commands?from='.now()->subDays(2)->toDateString())
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.rows.0.equipment_imei', '123456789012345')
            ->assertJsonPath('data.rows.0.vehicle', 'ABC1D23 - Gol')
            ->assertJsonPath('data.rows.0.command_label', 'Bloquear Motor')
            ->assertJsonPath('data.rows.0.user', $admin->name)
            ->assertJsonPath('data.rows.0.status_label', 'Enviado');
    }

    public function test_commands_report_filters_by_vehicle(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicleA = Vehicle::factory()->forClient($client)->create(['plate' => 'AAA1111', 'model' => 'Gol']);
        $vehicleB = Vehicle::factory()->forClient($client)->create(['plate' => 'BBB2222', 'model' => 'Uno']);

        DeviceCommandLog::factory()->forTenant($tenant)->forEquipment(Equipment::factory()->assignedTo($vehicleA)->create())->create();
        DeviceCommandLog::factory()->forTenant($tenant)->forEquipment(Equipment::factory()->assignedTo($vehicleB)->create())->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/reports/commands?vehicle_id='.$vehicleA->uuid)
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.rows.0.vehicle', 'AAA1111 - Gol');
    }

    public function test_commands_report_auto_filters_by_client_for_portal_user(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $vehicleA = Vehicle::factory()->forClient($clientA)->create(['plate' => 'AAA1111', 'model' => 'Gol']);
        $vehicleB = Vehicle::factory()->forClient($clientB)->create(['plate' => 'BBB2222', 'model' => 'Uno']);

        DeviceCommandLog::factory()->forTenant($tenant)->forEquipment(Equipment::factory()->assignedTo($vehicleA)->create())->create();
        DeviceCommandLog::factory()->forTenant($tenant)->forEquipment(Equipment::factory()->assignedTo($vehicleB)->create())->create();

        $portalUser = User::factory()->for($tenant)->create([
            'client_id' => $clientA->getKey(),
            'email' => 'portal@cliente.test',
        ]);
        $role = $this->roleFor($tenant, DefaultRole::CLIENT);
        $role->grantPermissions(Permission::REPORT_VIEW);
        $portalUser->assignRole($role);

        Sanctum::actingAs($portalUser);

        $this->getJson('/api/reports/commands')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.rows.0.vehicle', 'AAA1111 - Gol');
    }

    public function test_commands_report_exports_xlsx(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        DeviceCommandLog::factory()->forTenant($tenant)->forEquipment(Equipment::factory()->assignedTo($vehicle)->create())->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->get('/api/reports/commands/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
        );
    }

    public function test_positions_report_requires_vehicle(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/reports/positions')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
    }

    public function test_positions_report_lists_rows_for_vehicle(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23', 'model' => 'Gol']);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'latitude' => -25.4284,
            'longitude' => -49.2733,
            'speed' => 42.5,
            'ignition' => true,
            'battery' => 87,
            'valid' => true,
            'address' => 'Rua XV de Novembro',
            'recorded_at' => now()->subHour(),
            'attributes' => [
                'totalDistance' => 12345,
                'hours' => 678,
                'odometer' => 12000,
                'power' => 12.6,
                'blocked' => false,
            ],
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/reports/positions?vehicle_id='.$vehicle->uuid)
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.vehicle', 'ABC1D23 - Gol')
            ->assertJsonPath('data.rows.0.speed', 42.5)
            ->assertJsonPath('data.rows.0.ignition', true)
            ->assertJsonPath('data.rows.0.gps_status', true)
            ->assertJsonPath('data.rows.0.address', 'Rua XV de Novembro')
            ->assertJsonPath('data.rows.0.period_odometer', 12345)
            ->assertJsonPath('data.rows.0.onboard_odometer', 12000)
            ->assertJsonPath('data.rows.0.voltage', 12.6)
            ->assertJsonPath('data.rows.0.blocked', false);
    }

    public function test_positions_report_exports_xlsx(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        GpsPosition::factory()->forVehicle($vehicle)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->get('/api/reports/positions/export?vehicle_id='.$vehicle->uuid);

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
        );
    }
}
