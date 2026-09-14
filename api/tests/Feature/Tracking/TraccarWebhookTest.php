<?php

namespace Tests\Feature\Tracking;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TraccarWebhookTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private function positionPayload(int $id, int $deviceId): array
    {
        return [
            'position' => [
                'id' => $id,
                'deviceId' => $deviceId,
                'protocol' => 'osmand',
                'serverTime' => '2026-09-14T21:00:00.000+00:00',
                'deviceTime' => '2026-09-14T21:00:00.000+00:00',
                'fixTime' => '2026-09-14T21:00:00.000+00:00',
                'valid' => true,
                'latitude' => -25.4284,
                'longitude' => -49.2733,
                'altitude' => 934.0,
                'speed' => 1.85,
                'course' => 90.0,
                'address' => 'Rua XV de Novembro',
                'accuracy' => 10.0,
                'attributes' => [
                    'ignition' => true,
                    'batteryLevel' => 87,
                ],
            ],
            'device' => [
                'id' => $deviceId,
                'name' => 'Dispositivo',
                'uniqueId' => '123456789012345',
            ],
        ];
    }

    public function test_webhook_persists_position_for_known_device(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        $this->postJson('/api/webhooks/traccar', $this->positionPayload(12345, 42))
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        $this->assertDatabaseHas('gps_positions', [
            'tenant_id' => $tenant->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'traccar_position_id' => 12345,
            'latitude' => -25.4284,
        ]);
    }

    public function test_webhook_is_idempotent(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        $payload = $this->positionPayload(12345, 42);

        $this->postJson('/api/webhooks/traccar', $payload)->assertOk();
        $this->postJson('/api/webhooks/traccar', $payload)->assertOk();

        $this->assertSame(1, GpsPosition::query()->where('traccar_position_id', 12345)->count());
    }

    public function test_webhook_ignores_unknown_device(): void
    {
        [, $tenant] = $this->createOperationalChild();

        $this->postJson('/api/webhooks/traccar', $this->positionPayload(999, 999))
            ->assertOk();

        $this->assertSame(0, GpsPosition::query()->count());
    }

    public function test_webhook_requires_secret_when_configured(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        config()->set('traccar.webhook_secret', 'secret-token');

        $this->postJson('/api/webhooks/traccar', $this->positionPayload(12345, 42))
            ->assertForbidden();

        $this->withHeader('X-Traccar-Webhook-Token', 'secret-token')
            ->postJson('/api/webhooks/traccar', $this->positionPayload(12345, 42))
            ->assertOk();

        $this->assertSame(1, GpsPosition::query()->where('traccar_position_id', 12345)->count());
    }
}
