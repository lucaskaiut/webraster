<?php

namespace Tests\Feature\Client;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\Driver\Models\Driver;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientScopeIsolationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_client_portal_user_only_sees_own_drivers(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create(['name' => 'Cliente A']);
        $clientB = Client::factory()->for($tenant)->create(['name' => 'Cliente B']);

        $driverA = Driver::factory()->forClient($clientA)->create(['name' => 'Motorista A']);
        Driver::factory()->forClient($clientB)->create(['name' => 'Motorista B']);

        $portalUser = $this->createPortalUser($tenant, $clientA, [Permission::DRIVER_READ]);

        Sanctum::actingAs($portalUser);

        $response = $this->getJson('/api/drivers')->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Motorista A'));
        $this->assertFalse($names->contains('Motorista B'));
        $this->assertCount(1, $names);

        $this->getJson("/api/drivers/{$driverA->uuid}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Motorista A');
    }

    public function test_client_portal_user_cannot_access_other_client_driver(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $foreignDriver = Driver::factory()->forClient($clientB)->create();

        Sanctum::actingAs($this->createPortalUser($tenant, $clientA, [Permission::DRIVER_READ]));

        $this->getJson("/api/drivers/{$foreignDriver->uuid}")->assertNotFound();
    }

    public function test_staff_user_still_sees_all_tenant_drivers(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();

        Driver::factory()->forClient($clientA)->create(['name' => 'Motorista A']);
        Driver::factory()->forClient($clientB)->create(['name' => 'Motorista B']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $names = collect($this->getJson('/api/drivers')->assertOk()->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Motorista A'));
        $this->assertTrue($names->contains('Motorista B'));
    }

    public function test_client_portal_user_only_sees_own_client_record(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create(['name' => 'Cliente A']);
        Client::factory()->for($tenant)->create(['name' => 'Cliente B']);

        Sanctum::actingAs($this->createPortalUser($tenant, $clientA, [Permission::CLIENT_READ]));

        $response = $this->getJson('/api/clients')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Cliente A'));
        $this->assertFalse($names->contains('Cliente B'));
        $this->assertCount(1, $names);
    }

    /**
     * @param  list<Permission>  $permissions
     */
    private function createPortalUser($tenant, Client $client, array $permissions): User
    {
        $user = User::factory()->for($tenant)->create([
            'client_id' => $client->getKey(),
            'email' => 'portal-'.$client->getKey().'@cliente.test',
        ]);

        $role = $this->roleFor($tenant, DefaultRole::CLIENT);
        $role->grantPermissions(...$permissions);
        $user->assignRole($role);

        return $user;
    }
}
