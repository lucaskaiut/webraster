<?php

namespace Tests\Feature\Tracking;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Tracking\DTOs\TraccarPosition;
use App\Modules\Tracking\Enums\TraccarEventStatus;
use App\Modules\Tracking\Jobs\DispatchPendingTraccarEventsJob;
use App\Modules\Tracking\Jobs\ProcessTraccarEvent;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Tracking\Models\TraccarEvent;
use App\Modules\Tracking\Services\TrackingService;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TraccarWebhookTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private function positionPayload(int $id, int $deviceId, ?string $deviceTime = null): array
    {
        $deviceTime ??= '2026-09-14T21:00:00.000+00:00';

        return [
            'position' => [
                'id' => $id,
                'deviceId' => $deviceId,
                'protocol' => 'osmand',
                'serverTime' => $deviceTime,
                'deviceTime' => $deviceTime,
                'fixTime' => $deviceTime,
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
            'address' => 'Rua XV de Novembro',
        ]);

        $equipment = Equipment::query()->where('traccar_device_id', 42)->firstOrFail();
        $this->assertSame('online', $equipment->traccar_status);
        $this->assertSame(
            '2026-09-14 21:00:00',
            $equipment->traccar_last_update?->utc()->format('Y-m-d H:i:s'),
        );
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
        $this->assertSame(
            TraccarEventStatus::IGNORED,
            TraccarEvent::query()->firstOrFail()->status,
        );
    }

    public function test_webhook_stores_event_in_inbox_before_processing(): void
    {
        Queue::fake();

        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        $this->postJson('/api/webhooks/traccar', $this->positionPayload(12345, 42))
            ->assertOk();

        $event = TraccarEvent::query()->firstOrFail();

        $this->assertSame(TraccarEventStatus::PENDING, $event->status);
        $this->assertNotNull($event->dispatched_at);
        $this->assertSame(42, $event->traccar_device_id);
        $this->assertSame(0, GpsPosition::query()->count());

        Queue::assertPushed(
            ProcessTraccarEvent::class,
            fn (ProcessTraccarEvent $job) => $job->eventId === $event->getKey(),
        );
    }

    public function test_position_without_traccar_id_uses_natural_key(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        $first = $this->positionPayload(0, 42, '2026-09-14T21:00:00.000+00:00');
        $second = $this->positionPayload(0, 42, '2026-09-14T21:00:10.000+00:00');

        $this->postJson('/api/webhooks/traccar', $first)->assertOk();
        $this->postJson('/api/webhooks/traccar', $first)->assertOk();
        $this->postJson('/api/webhooks/traccar', $second)->assertOk();

        $this->assertSame(2, GpsPosition::query()->count());
        $this->assertSame(2, GpsPosition::query()->whereNull('traccar_position_id')->count());
    }

    public function test_distinct_packets_with_same_time_are_merged(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        $location = $this->positionPayload(0, 42);
        $location['position']['protocol'] = 'gt06';
        $location['position']['attributes'] = [
            'type' => 34,
            'ignition' => false,
            'raw' => 'hex:787826220a',
        ];

        $alarm = $this->positionPayload(0, 42, '2026-09-14T21:00:00.500+00:00');
        $alarm['position']['protocol'] = 'gt06';
        $alarm['position']['attributes'] = [
            'type' => 38,
            'ignition' => true,
            'charge' => false,
            'blocked' => false,
            'rssi' => 3,
            'batteryLevel' => 16,
            'alarm' => 'powerCut,powerCut',
            'raw' => 'hex:787829260b',
        ];

        $this->postJson('/api/webhooks/traccar', $location)->assertOk();
        $this->postJson('/api/webhooks/traccar', $alarm)->assertOk();

        $this->assertSame(2, TraccarEvent::query()->count());
        $this->assertSame(1, GpsPosition::query()->count());

        $position = GpsPosition::query()->firstOrFail();
        $this->assertTrue($position->ignition);
        $this->assertSame(16.0, (float) $position->battery);
        $this->assertSame('hex:787826220a', $position->attributes['raw']);
        $this->assertFalse($position->attributes['charge']);
        $this->assertSame(3, $position->attributes['rssi']);
        $this->assertSame('powerCut', $position->attributes['alarm']);

        // Reentrega idêntica é deduplicada no webhook.
        $this->postJson('/api/webhooks/traccar', $location)->assertOk();
        $this->assertSame(2, TraccarEvent::query()->count());

        // Resync do primeiro pacote (SyncTraccarPositionsJob) não apaga o que foi mesclado.
        $equipment = Equipment::query()->where('traccar_device_id', 42)->firstOrFail();
        $location['position']['id'] = 777;
        TenantContext::set($equipment->tenant);
        app(TrackingService::class)->persistPosition(
            $vehicle,
            $equipment,
            TraccarPosition::fromArray($location['position']),
        );
        TenantContext::forget();

        $position->refresh();
        $this->assertSame(1, GpsPosition::query()->count());
        $this->assertTrue($position->ignition);
        $this->assertFalse($position->attributes['charge']);
        $this->assertSame('powerCut', $position->attributes['alarm']);
    }

    public function test_latest_packet_in_the_same_second_wins_on_conflict(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        $packet = function (string $time, bool $blocked, string $raw, array $extra = []): array {
            $payload = $this->positionPayload(0, 42, $time);
            $payload['position']['protocol'] = 'gt06';
            $payload['position']['attributes'] = ['type' => 19, 'blocked' => $blocked, 'raw' => $raw, ...$extra];

            return $payload;
        };

        // Heartbeat bloqueado, resposta do desbloqueio e heartbeat desbloqueado no mesmo segundo.
        $blockedHeartbeat = $packet('2026-09-14T21:00:12.262+00:00', true, 'hex:78780a13c6');
        $result = $packet('2026-09-14T21:00:12.496+00:00', true, 'hex:7878161552', ['result' => 'RELAY 0 OK']);
        $unblockedHeartbeat = $packet('2026-09-14T21:00:12.519+00:00', false, 'hex:78780a1346');

        $this->postJson('/api/webhooks/traccar', $blockedHeartbeat)->assertOk();
        $this->postJson('/api/webhooks/traccar', $result)->assertOk();
        $this->postJson('/api/webhooks/traccar', $unblockedHeartbeat)->assertOk();

        $position = GpsPosition::query()->sole();
        $this->assertFalse($position->attributes['blocked']);
        $this->assertSame('RELAY 0 OK', $position->attributes['result']);

        // Resync do heartbeat bloqueado (mais antigo) não reverte o estado.
        $equipment = Equipment::query()->where('traccar_device_id', 42)->firstOrFail();
        TenantContext::set($equipment->tenant);
        app(TrackingService::class)->persistPosition(
            $vehicle,
            $equipment,
            TraccarPosition::fromArray($blockedHeartbeat['position']),
        );
        TenantContext::forget();

        $this->assertFalse($position->refresh()->attributes['blocked']);
    }

    public function test_unassigned_device_records_position_for_stock_equipment(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $equipment = Equipment::factory()->create(['traccar_device_id' => 42]);

        $this->postJson('/api/webhooks/traccar', $this->positionPayload(12345, 42))
            ->assertOk();

        $event = TraccarEvent::query()->firstOrFail();

        $this->assertSame(TraccarEventStatus::PROCESSED, $event->status);
        $this->assertSame(1, GpsPosition::query()->count());

        $position = GpsPosition::query()->firstOrFail();
        $this->assertNull($position->vehicle_id);
        $this->assertNull($position->client_id);
        $this->assertSame($equipment->getKey(), $position->equipment_id);
    }

    public function test_pending_events_are_requeued(): void
    {
        Queue::fake();

        $event = TraccarEvent::query()->create([
            'traccar_device_id' => 42,
            'dedupe_key' => hash('sha256', 'pending-event-test'),
            'status' => TraccarEventStatus::PENDING,
            'payload' => [
                'position' => [
                    'deviceId' => 42,
                    'deviceTime' => '2026-09-14T21:00:00.000+00:00',
                ],
            ],
            'received_at' => now()->subMinutes(10),
        ]);

        (new DispatchPendingTraccarEventsJob)->handle();

        Queue::assertPushed(
            ProcessTraccarEvent::class,
            fn (ProcessTraccarEvent $job) => $job->eventId === $event->getKey(),
        );
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
