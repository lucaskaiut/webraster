<?php

namespace Tests\Feature\Tracking;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Events\PositionUpdated;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
use App\Modules\Tracking\Jobs\SyncTraccarPositionsJob;
use App\Modules\Tracking\Services\TrackingService;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SyncTraccarPositionsTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_sync_persists_positions_pulled_from_traccar(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '359633100000077',
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
                    'id' => 3001,
                    'deviceId' => 55,
                    'latitude' => -25.43,
                    'longitude' => -49.27,
                    'deviceTime' => '2026-09-04T10:00:00Z',
                    'attributes' => ['ignition' => true],
                ],
            ]),
        ]);

        (new SyncTraccarPositionsJob)->handle(
            $this->app->make(TraccarGateway::class),
            $this->app->make(TrackingService::class),
        );

        $this->assertDatabaseHas('gps_positions', [
            'equipment_id' => $equipment->getKey(),
            'traccar_position_id' => 3001,
            'latitude' => -25.43,
        ]);
    }

    public function test_sync_broadcasts_position_updates_to_tenant_and_client_channels(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '359633100000078',
            'traccar_device_id' => 56,
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
                    'id' => 3002,
                    'deviceId' => 56,
                    'latitude' => -25.44,
                    'longitude' => -49.26,
                    'deviceTime' => '2026-09-04T10:05:00Z',
                    'attributes' => ['ignition' => true],
                ],
            ]),
        ]);

        Event::fake([PositionUpdated::class]);

        (new SyncTraccarPositionsJob)->handle(
            $this->app->make(TraccarGateway::class),
            $this->app->make(TrackingService::class),
        );

        Event::assertDispatched(PositionUpdated::class, function (PositionUpdated $event) use ($tenant, $client, $vehicle): bool {
            $channels = array_map(
                fn ($channel): string => (string) $channel,
                $event->broadcastOn(),
            );

            return $event->payload['vehicle_id'] === $vehicle->uuid
                && $event->payload['online'] === true
                && $event->payload['position']['latitude'] === -25.44
                && in_array("private-tenant.{$tenant->uuid}.staff", $channels, true)
                && in_array("private-client.{$client->uuid}", $channels, true);
        });
    }
}
