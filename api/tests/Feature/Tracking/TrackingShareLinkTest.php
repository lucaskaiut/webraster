<?php

namespace Tests\Feature\Tracking;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Tracking\Models\TrackingShareLink;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TrackingShareLinkTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: Vehicle, 2: Vehicle}
     */
    private function setUpFleet(): array
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $first = Vehicle::factory()->forClient($client)->create(['plate' => 'AAA1A11']);
        $second = Vehicle::factory()->forClient($client)->create(['plate' => 'BBB2B22']);
        Equipment::factory()->assignedTo($first)->create(['traccar_device_id' => 1]);
        Equipment::factory()->assignedTo($second)->create(['traccar_device_id' => 2]);

        GpsPosition::factory()->forVehicle($first)->create([
            'latitude' => -25.4284,
            'longitude' => -49.2733,
            'recorded_at' => now()->subMinute(),
        ]);

        return [$tenant, $first, $second];
    }

    private function createLink(array $payload): string
    {
        $token = $this->postJson('/api/tracking/share-links', $payload)
            ->assertCreated()
            ->json('data.token');

        // A rota pública é acessada sem sessão nem contexto de tenant.
        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->forgetTenantContext();

        return $token;
    }

    public function test_admin_creates_link_and_public_page_returns_only_shared_vehicles(): void
    {
        [$tenant, $first, $second] = $this->setUpFleet();
        Sanctum::actingAs($this->createAdmin($tenant));

        $token = $this->createLink(['vehicle_ids' => [$first->uuid], 'expires_in_hours' => 2]);

        $response = $this->getJson("/api/public/tracking/{$token}")
            ->assertOk()
            ->assertJsonCount(1, 'data.vehicles')
            ->assertJsonPath('data.vehicles.0.plate', 'AAA1A11')
            ->assertJsonPath('data.vehicles.0.online', true)
            ->assertJsonPath('data.vehicles.0.position.latitude', -25.4284)
            ->assertJsonPath('data.tenant.name', $tenant->name);

        // Sem dados de cliente, motorista ou equipamento.
        $vehicle = $response->json('data.vehicles.0');
        $this->assertArrayNotHasKey('client', $vehicle);
        $this->assertArrayNotHasKey('driver', $vehicle);
        $this->assertArrayNotHasKey('equipment', $vehicle);
        $this->assertArrayNotHasKey('battery', $vehicle['position']);
        $this->assertNotContains($second->plate, array_column($response->json('data.vehicles'), 'plate'));
    }

    public function test_link_can_share_multiple_vehicles(): void
    {
        [$tenant, $first, $second] = $this->setUpFleet();
        Sanctum::actingAs($this->createAdmin($tenant));

        $token = $this->createLink(['vehicle_ids' => [$first->uuid, $second->uuid], 'expires_in_hours' => 24]);

        $this->getJson("/api/public/tracking/{$token}")
            ->assertOk()
            ->assertJsonCount(2, 'data.vehicles')
            ->assertJsonPath('data.vehicles.1.position', null);
    }

    public function test_expiration_cannot_exceed_24_hours(): void
    {
        [$tenant, $first] = $this->setUpFleet();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/tracking/share-links', ['vehicle_ids' => [$first->uuid], 'expires_in_hours' => 25])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_in_hours');

        $this->postJson('/api/tracking/share-links', ['vehicle_ids' => [], 'expires_in_hours' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_ids');
    }

    public function test_expired_link_returns_gone(): void
    {
        [$tenant, $first] = $this->setUpFleet();
        Sanctum::actingAs($this->createAdmin($tenant));

        $token = $this->createLink(['vehicle_ids' => [$first->uuid], 'expires_in_hours' => 1]);

        $this->travel(61)->minutes();

        $this->getJson("/api/public/tracking/{$token}")->assertStatus(410);
    }

    public function test_unknown_token_returns_not_found(): void
    {
        $this->getJson('/api/public/tracking/invalid-token')->assertNotFound();
    }

    public function test_token_is_stored_hashed(): void
    {
        [$tenant, $first] = $this->setUpFleet();
        Sanctum::actingAs($this->createAdmin($tenant));

        $token = $this->createLink(['vehicle_ids' => [$first->uuid], 'expires_in_hours' => 1]);

        $link = TrackingShareLink::query()->withoutGlobalScopes()->sole();
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertNotSame($token, $link->token_hash);
    }

    public function test_cannot_share_vehicle_from_another_tenant(): void
    {
        [$tenant] = $this->setUpFleet();
        [, $otherTenant] = $this->createOperationalChild();
        $otherVehicle = Vehicle::factory()
            ->forClient(Client::factory()->for($otherTenant)->create())
            ->create();
        $this->forgetTenantContext();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/tracking/share-links', ['vehicle_ids' => [$otherVehicle->uuid], 'expires_in_hours' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_ids');
    }

    public function test_member_without_permission_cannot_create_link(): void
    {
        [$tenant, $first] = $this->setUpFleet();
        Sanctum::actingAs($this->createMember($tenant));

        $this->postJson('/api/tracking/share-links', ['vehicle_ids' => [$first->uuid], 'expires_in_hours' => 1])
            ->assertForbidden();
    }
}
