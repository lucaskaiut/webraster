<?php

namespace Tests\Feature\Tracking;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
use App\Modules\Tracking\Jobs\SyncTraccarDevicesJob;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SyncTraccarDevicesTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_sync_stores_status_and_last_update_from_traccar(): void
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
            'traccar.test/api/devices*' => Http::response([
                [
                    'id' => 55,
                    'uniqueId' => '359633100000077',
                    'name' => 'Tracker 55',
                    'status' => 'online',
                    'lastUpdate' => '2026-09-21T16:10:15.000+00:00',
                ],
            ]),
        ]);

        (new SyncTraccarDevicesJob)->handle($this->app->make(TraccarGateway::class));

        $equipment->refresh();

        $this->assertSame('online', $equipment->traccar_status);
        $this->assertSame(
            '2026-09-21 16:10:15',
            $equipment->traccar_last_update?->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_sync_leaves_equipment_without_matching_device_untouched(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '359633100000088',
            'traccar_device_id' => 99,
        ]);

        config([
            'traccar.enabled' => true,
            'traccar.base_url' => 'http://traccar.test',
            'traccar.token' => 'test-token',
        ]);

        $this->app->instance(TraccarGateway::class, $this->app->make(HttpTraccarGateway::class));

        Http::fake([
            'traccar.test/api/devices*' => Http::response([
                [
                    'id' => 55,
                    'uniqueId' => '359633100000077',
                    'name' => 'Tracker 55',
                    'status' => 'online',
                    'lastUpdate' => '2026-09-21T16:10:15.000+00:00',
                ],
            ]),
        ]);

        (new SyncTraccarDevicesJob)->handle($this->app->make(TraccarGateway::class));

        $equipment->refresh();

        $this->assertNull($equipment->traccar_status);
        $this->assertNull($equipment->traccar_last_update);
    }
}
