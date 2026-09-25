<?php

namespace Tests\Feature\Tracking;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
use App\Modules\Tracking\Gateways\NullTraccarGateway;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TrackingLiveTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_live_uses_recent_traccar_signal_when_position_is_stale(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23']);
        Equipment::factory()->assignedTo($vehicle)->create([
            'traccar_device_id' => 42,
            'traccar_status' => 'offline',
            'traccar_last_update' => now()->subMinutes(2),
        ]);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'recorded_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')
            ->assertOk()
            ->assertJsonPath('data.0.online', true);
    }

    public function test_live_is_offline_when_position_and_traccar_signal_are_stale(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23']);
        Equipment::factory()->assignedTo($vehicle)->create([
            'traccar_device_id' => 42,
            'traccar_last_update' => now()->subMinutes(30),
        ]);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'recorded_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')
            ->assertOk()
            ->assertJsonPath('data.0.online', false);
    }

    public function test_live_reads_latest_position_from_database(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23', 'vehicle_type' => 1]);
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'latitude' => -25.4284,
            'longitude' => -49.2733,
            'ignition' => true,
            'recorded_at' => now()->subMinute(),
        ]);

        VehicleImage::factory()->forVehicle($vehicle)->create(['path' => 'uploads/frota.jpg']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')
            ->assertOk()
            ->assertJsonPath('data.0.id', $vehicle->uuid)
            ->assertJsonPath('data.0.plate', 'ABC1D23')
            ->assertJsonPath('data.0.vehicle_type', 1)
            ->assertJsonPath('data.0.images.0.path', 'uploads/frota.jpg')
            ->assertJsonPath('data.0.online', true)
            ->assertJsonPath('data.0.position.latitude', -25.4284)
            ->assertJsonPath('data.0.position.longitude', -49.2733)
            ->assertJsonPath('data.0.position.ignition', true);
    }

    public function test_live_uses_most_recent_recorded_at_not_highest_id(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23']);
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        // Linha mais nova (recorded_at) gravada antes; o backfill insere depois
        // uma linha mais antiga com ID maior — o painel deve usar a mais recente.
        GpsPosition::factory()->forVehicle($vehicle)->create([
            'latitude' => -25.1,
            'recorded_at' => now()->subMinutes(2),
        ]);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'latitude' => -25.9,
            'recorded_at' => now()->subMinutes(10),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')
            ->assertOk()
            ->assertJsonPath('data.0.position.latitude', -25.1);
    }

    public function test_live_exposes_device_alarms_from_persisted_attributes(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'PWR1234']);
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 77]);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'recorded_at' => now()->subMinute(),
            'attributes' => [
                'alarm' => 'powerCut',
                'charge' => false,
            ],
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')
            ->assertOk()
            ->assertJsonPath('data.0.position.alarms.0.code', 'powercut')
            ->assertJsonPath('data.0.position.alarms.0.label', 'Alimentação cortada')
            ->assertJsonPath('data.0.position.alarms.0.severity', 'critical');
    }

    public function test_live_exposes_device_telemetry_attributes(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'TEL1234']);
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 78]);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'recorded_at' => now()->subMinute(),
            'valid' => true,
            'attributes' => [
                'rssi' => 23,
                'sat' => 19,
                'adc1' => 14.01,
                'blocked' => false,
                'hours' => 2582580000,
                'totalDistance' => 349709.57,
            ],
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')
            ->assertOk()
            ->assertJsonPath('data.0.position.valid', true)
            ->assertJsonPath('data.0.position.signal', 23)
            ->assertJsonPath('data.0.position.satellites', 19)
            ->assertJsonPath('data.0.position.voltage', 14.01)
            ->assertJsonPath('data.0.position.external_power', true)
            ->assertJsonPath('data.0.position.blocked', false)
            ->assertJsonPath('data.0.position.hours', 717.38)
            ->assertJsonPath('data.0.position.odometer', 349709.57);
    }

    public function test_live_resolves_missing_traccar_device_id(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23']);
        $equipment = Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '359633100000099',
        ]);

        config([
            'traccar.enabled' => true,
            'traccar.base_url' => 'http://traccar.test',
            'traccar.token' => 'test-token',
            'traccar.timeout' => 5,
        ]);

        $this->app->instance(TraccarGateway::class, $this->app->make(HttpTraccarGateway::class));

        Http::fake([
            'traccar.test/api/devices*' => Http::response([
                [
                    'id' => 42,
                    'uniqueId' => '359633100000099',
                    'name' => 'Tracker 99',
                    'status' => 'online',
                ],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')->assertOk();

        $this->assertDatabaseHas('equipments', [
            'id' => $equipment->getKey(),
            'traccar_device_id' => 42,
        ]);
    }

    public function test_history_returns_route_points(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '359633100000088',
            'traccar_device_id' => 55,
        ]);

        config([
            'traccar.enabled' => true,
            'traccar.base_url' => 'http://traccar.test',
            'traccar.token' => 'test-token',
        ]);

        $this->app->instance(TraccarGateway::class, $this->app->make(HttpTraccarGateway::class));

        Http::fake([
            'traccar.test/api/positions*' => Http::response([
                [
                    'id' => 2001,
                    'deviceId' => 55,
                    'latitude' => -25.43,
                    'longitude' => -49.27,
                    'deviceTime' => '2026-09-04T10:00:00Z',
                    'speed' => 10,
                    'attributes' => ['ignition' => true],
                ],
                [
                    'id' => 2002,
                    'deviceId' => 55,
                    'latitude' => -25.431,
                    'longitude' => -49.271,
                    'deviceTime' => '2026-09-04T10:05:00Z',
                    'speed' => 20,
                    'attributes' => ['ignition' => true],
                ],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson("/api/tracking/vehicles/{$vehicle->uuid}/history?from=2026-09-04T00:00:00Z&to=2026-09-04T23:59:59Z")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.latitude', -25.43)
            ->assertJsonPath('data.1.latitude', -25.431);
    }

    public function test_history_backfills_remote_positions_in_bulk_and_only_once(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '359633100000055',
            'traccar_device_id' => 55,
        ]);

        config([
            'traccar.enabled' => true,
            'traccar.base_url' => 'http://traccar.test',
            'traccar.token' => 'test-token',
        ]);

        $this->app->instance(TraccarGateway::class, $this->app->make(HttpTraccarGateway::class));

        Http::fake([
            'traccar.test/api/positions*' => Http::response([
                [
                    'id' => 2001,
                    'deviceId' => 55,
                    'latitude' => -25.43,
                    'longitude' => -49.27,
                    'deviceTime' => '2026-09-04T10:00:00Z',
                    'speed' => 10,
                    'attributes' => ['ignition' => true],
                ],
                [
                    'id' => 2002,
                    'deviceId' => 55,
                    'latitude' => -25.431,
                    'longitude' => -49.271,
                    'deviceTime' => '2026-09-04T10:05:00Z',
                    'speed' => 20,
                    'attributes' => ['ignition' => true],
                ],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $url = "/api/tracking/vehicles/{$vehicle->uuid}/history?from=2026-09-04T00:00:00Z&to=2026-09-04T23:59:59Z";

        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data');

        Http::assertSentCount(1);

        $this->assertDatabaseCount('gps_positions', 2);

        $this->assertDatabaseHas('gps_positions', [
            'equipment_id' => $equipment->getKey(),
            'traccar_position_id' => 2001,
            'latitude' => -25.43,
        ]);
    }

    public function test_history_reads_persisted_positions_without_consulting_traccar(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '359633100000099',
            'traccar_device_id' => 99,
        ]);

        GpsPosition::factory()->forVehicle($vehicle)->create([
            'latitude' => -25.5,
            'longitude' => -49.3,
            'recorded_at' => '2026-09-04T10:00:00Z',
        ]);

        config([
            'traccar.enabled' => true,
            'traccar.base_url' => 'http://traccar.test',
            'traccar.token' => 'test-token',
        ]);

        $this->app->instance(TraccarGateway::class, $this->app->make(HttpTraccarGateway::class));

        Http::fake();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson("/api/tracking/vehicles/{$vehicle->uuid}/history?from=2026-09-04T00:00:00Z&to=2026-09-04T23:59:59Z")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.latitude', -25.5);

        Http::assertNothingSent();
    }

    public function test_portal_client_only_sees_own_vehicles(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();

        $vehicleA = Vehicle::factory()->forClient($clientA)->create(['plate' => 'AAA1111']);
        Equipment::factory()->assignedTo($vehicleA)->create();

        $vehicleB = Vehicle::factory()->forClient($clientB)->create(['plate' => 'BBB2222']);
        Equipment::factory()->assignedTo($vehicleB)->create();

        $portalUser = User::factory()->for($tenant)->create([
            'client_id' => $clientA->getKey(),
            'email' => 'portal-tracking@cliente.test',
        ]);

        $role = $this->roleFor($tenant, DefaultRole::CLIENT);
        $role->grantPermissions(Permission::TRACKING_READ);
        $portalUser->assignRole($role);

        Sanctum::actingAs($portalUser);

        $response = $this->getJson('/api/tracking/live')->assertOk();
        $plates = collect($response->json('data'))->pluck('plate');

        $this->assertTrue($plates->contains('AAA1111'));
        $this->assertFalse($plates->contains('BBB2222'));
    }

    public function test_status_reports_gateway_configuration(): void
    {
        config([
            'traccar.enabled' => false,
            'traccar.token' => null,
            'traccar.email' => null,
            'traccar.password' => null,
        ]);

        $this->app->instance(
            TraccarGateway::class,
            $this->app->make(NullTraccarGateway::class),
        );

        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.configured', false);
    }
}
