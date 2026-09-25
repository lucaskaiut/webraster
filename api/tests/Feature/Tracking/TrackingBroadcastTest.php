<?php

namespace Tests\Feature\Tracking;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Events\PositionUpdated;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TrackingBroadcastTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        require base_path('routes/channels.php');
    }

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

    public function test_position_update_broadcasts_to_tenant_and_client_channels(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create(['traccar_device_id' => 42]);

        Event::fake([PositionUpdated::class]);

        $this->postJson('/api/webhooks/traccar', $this->positionPayload(12345, 42))->assertOk();

        Event::assertDispatched(PositionUpdated::class, function (PositionUpdated $event) use ($tenant, $client, $vehicle): bool {
            $channels = array_map(
                fn ($channel): string => (string) $channel,
                $event->broadcastOn(),
            );

            return $event->payload['vehicle_id'] === $vehicle->uuid
                && $event->payload['online'] === true
                && $event->payload['position']['latitude'] === -25.4284
                && in_array("private-tenant.{$tenant->uuid}.staff", $channels, true)
                && in_array("private-client.{$client->uuid}", $channels, true);
        });
    }

    public function test_broadcast_auth_authorizes_staff_of_accessible_tenants_only(): void
    {
        [$umbrella, $tenant] = $this->createOperationalChild();
        $other = $this->createChildTenant($umbrella);

        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$tenant->uuid}.staff",
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$other->uuid}.staff",
        ])->assertForbidden();
    }

    public function test_broadcast_auth_authorizes_client_user_only_for_own_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $otherClient = Client::factory()->for($tenant)->create();

        $user = $this->createClient($tenant);
        $user->forceFill(['client_id' => $client->getKey()])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-client.{$client->uuid}",
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-client.{$otherClient->uuid}",
        ])->assertForbidden();
    }

    public function test_broadcast_auth_blocks_staff_from_client_channels(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-client.{$client->uuid}",
        ])->assertForbidden();
    }
}
