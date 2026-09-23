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
                'hours' => 2582580000,
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
            ->assertJsonPath('data.rows.0.period_horimeter', 717.38)
            ->assertJsonPath('data.rows.0.onboard_horimeter', 717.38)
            ->assertJsonPath('data.rows.0.onboard_odometer', 12)
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

    public function test_stops_report_aggregates_stops_per_vehicle(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23', 'model' => 'Gol']);
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        $base = now()->startOfDay()->addHours(8);

        // Parada de 2 minutos (contada).
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base, 'speed' => 50, 'ignition' => true]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->addMinutes(1), 'speed' => 0, 'ignition' => false]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->addMinutes(2), 'speed' => 0, 'ignition' => false]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->addMinutes(3), 'speed' => 0, 'ignition' => false]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->addMinutes(4), 'speed' => 60, 'ignition' => true]);

        // Parada de 1 minuto (abaixo da duração mínima, descartada).
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->addMinutes(5), 'speed' => 0, 'ignition' => false]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->addMinutes(6), 'speed' => 0, 'ignition' => false]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/reports/stops')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.rows.0.vehicle', 'ABC1D23 - Gol')
            ->assertJsonPath('data.rows.0.total_stops', 1)
            ->assertJsonPath('data.rows.0.total_stop_seconds', 120);
    }

    public function test_stops_report_exports_xlsx(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        GpsPosition::factory()->forVehicle($vehicle)->create(['speed' => 0, 'ignition' => false]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->get('/api/reports/stops/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
        );
    }

    public function test_trips_report_requires_vehicle(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/reports/trips')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
    }

    public function test_trips_report_lists_trips_between_stops(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23', 'model' => 'Gol']);
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        $base = now()->startOfDay()->addHours(8);

        // Percurso 1 (5 min em movimento).
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->subMinutes(30), 'speed' => 30, 'ignition' => true, 'latitude' => -25.5254, 'longitude' => -49.0946]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->subMinutes(25), 'speed' => 30, 'ignition' => true, 'latitude' => -25.5244, 'longitude' => -49.0936]);

        // Parada de 10 min.
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->subMinutes(20), 'speed' => 0, 'ignition' => false, 'latitude' => -25.5242, 'longitude' => -49.0934]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->subMinutes(15), 'speed' => 0, 'ignition' => false, 'latitude' => -25.5242, 'longitude' => -49.0934]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->subMinutes(10), 'speed' => 0, 'ignition' => false, 'latitude' => -25.5242, 'longitude' => -49.0934]);

        // Percurso 2 (3 min em movimento).
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->subMinutes(5), 'speed' => 30, 'ignition' => true, 'latitude' => -25.5234, 'longitude' => -49.0926]);
        GpsPosition::factory()->forVehicle($vehicle)->create(['recorded_at' => $base->copy()->subMinutes(2), 'speed' => 30, 'ignition' => true, 'latitude' => -25.5224, 'longitude' => -49.0916]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/reports/trips?vehicle_id='.$vehicle->uuid)
            ->assertOk()
            ->assertJsonPath('data.vehicle', 'ABC1D23 - Gol')
            ->assertJsonPath('data.summary.trips', 2)
            ->assertJsonPath('data.summary.total_moving_seconds', 480)
            ->assertJsonPath('data.summary.total_stopped_seconds', 600)
            ->assertJsonPath('data.trips.0.moving_seconds', 300)
            ->assertJsonPath('data.trips.0.following_stop_seconds', 600)
            ->assertJsonPath('data.trips.1.moving_seconds', 180)
            ->assertJsonPath('data.trips.1.following_stop_seconds', null);
    }
}
