<?php

namespace Tests\Feature\Tracking;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TrackingLiveTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_live_fetches_positions_from_traccar_gateway(): void
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
            'traccar.test/api/positions*' => Http::response([
                [
                    'id' => 1001,
                    'deviceId' => 42,
                    'latitude' => -25.4284,
                    'longitude' => -49.2733,
                    'deviceTime' => now()->toIso8601String(),
                    'speed' => 12.5,
                    'course' => 90,
                    'altitude' => 900,
                    'attributes' => [
                        'ignition' => true,
                        'batteryLevel' => 85.5,
                    ],
                ],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')
            ->assertOk()
            ->assertJsonPath('data.0.id', $vehicle->uuid)
            ->assertJsonPath('data.0.plate', 'ABC1D23')
            ->assertJsonPath('data.0.online', true)
            ->assertJsonPath('data.0.position.latitude', -25.4284)
            ->assertJsonPath('data.0.position.longitude', -49.2733)
            ->assertJsonPath('data.0.position.ignition', true);

        $this->assertDatabaseHas('equipments', [
            'id' => $equipment->getKey(),
            'traccar_device_id' => 42,
        ]);

        $this->assertDatabaseHas('gps_positions', [
            'vehicle_id' => $vehicle->getKey(),
            'client_id' => $client->getKey(),
            'traccar_position_id' => 1001,
        ]);
    }

    public function test_live_exposes_device_alarms_from_traccar_attributes(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'PWR1234']);
        Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '359633100000077',
            'traccar_device_id' => 77,
        ]);

        config([
            'traccar.enabled' => true,
            'traccar.base_url' => 'http://traccar.test',
            'traccar.token' => 'test-token',
            'traccar.timeout' => 5,
        ]);

        $this->app->instance(TraccarGateway::class, $this->app->make(HttpTraccarGateway::class));

        Http::fake([
            'traccar.test/api/positions*' => Http::response([
                [
                    'id' => 2001,
                    'deviceId' => 77,
                    'latitude' => -25.4284,
                    'longitude' => -49.2733,
                    'deviceTime' => now()->toIso8601String(),
                    'speed' => 0,
                    'attributes' => [
                        'alarm' => 'powerCut',
                        'charge' => false,
                    ],
                ],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/live')
            ->assertOk()
            ->assertJsonPath('data.0.position.alarms.0.code', 'powercut')
            ->assertJsonPath('data.0.position.alarms.0.label', 'Alimentação cortada')
            ->assertJsonPath('data.0.position.alarms.0.severity', 'critical');
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

        $role = $this->roleFor($tenant, \App\Modules\ACL\Enums\DefaultRole::CLIENT);
        $role->grantPermissions(\App\Modules\ACL\Enums\Permission::TRACKING_READ);
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
            $this->app->make(\App\Modules\Tracking\Gateways\NullTraccarGateway::class),
        );

        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tracking/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.configured', false);
    }
}
