<?php

namespace Tests\Feature\Client;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\Shared\Support\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_index_lists_only_clients_of_current_tenant(): void
    {
        [$umbrella, $tenant] = $this->createOperationalChild();
        $other = $this->createChildTenant($umbrella, ['domain' => 'outro.com.br']);

        Client::factory()->for($tenant)->create(['name' => 'Cliente A']);
        Client::factory()->for($other)->create(['name' => 'Cliente B']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/clients')->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Cliente A'));
        $this->assertFalse($names->contains('Cliente B'));
    }

    public function test_store_creates_client_for_current_tenant(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $payload = [
            'name' => 'Transportadora Silva',
            'document' => Document::fakeCnpj(),
            'email' => 'contato@silva.com',
            'phone' => '41999999999',
            'street' => 'Rua das Flores',
            'number' => '100',
            'city' => 'Curitiba',
            'state' => 'PR',
            'zip' => '80000000',
        ];

        $this->postJson('/api/clients', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Transportadora Silva')
            ->assertJsonPath('data.state', 'PR');

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $tenant->getKey(),
            'name' => 'Transportadora Silva',
            'document' => $payload['document'],
        ]);
    }

    public function test_update_and_destroy_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}", ['name' => 'Cliente Atualizado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cliente Atualizado');

        $this->deleteJson("/api/clients/{$client->uuid}")->assertOk();

        $this->assertSoftDeleted('clients', ['id' => $client->getKey()]);
    }

    public function test_client_users_crud(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $roleId = $this->roleFor($tenant, DefaultRole::CLIENT)->getKey();

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->postJson("/api/clients/{$client->uuid}/users", [
            'name' => 'Portal Cliente',
            'email' => 'portal@cliente.com',
            'password' => '12345678',
            'role_ids' => [$roleId],
        ])->assertCreated();

        $userId = $response->json('data.id');

        $this->assertDatabaseHas('users', [
            'email' => 'portal@cliente.com',
            'client_id' => $client->getKey(),
        ]);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonMissing(['email' => 'portal@cliente.com']);

        $this->getJson("/api/clients/{$client->uuid}/users")
            ->assertOk()
            ->assertJsonPath('data.0.email', 'portal@cliente.com');

        $this->putJson("/api/clients/{$client->uuid}/users/{$userId}", [
            'name' => 'Portal Atualizado',
        ])->assertOk()->assertJsonPath('data.name', 'Portal Atualizado');

        $this->deleteJson("/api/clients/{$client->uuid}/users/{$userId}")->assertOk();

        $this->assertSoftDeleted('users', ['email' => 'portal@cliente.com']);
    }

    public function test_cannot_access_client_of_other_tenant(): void
    {
        [$umbrella, $tenantA] = $this->createOperationalChild();
        $tenantB = $this->createChildTenant($umbrella, ['domain' => 'outro.com.br']);
        $foreign = Client::factory()->for($tenantB)->create();

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->getJson("/api/clients/{$foreign->uuid}")->assertNotFound();
    }

    public function test_member_without_permission_cannot_manage_clients(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createClient($tenant));

        $this->getJson('/api/clients')->assertForbidden();
        $this->postJson('/api/clients', ['name' => 'X'])->assertForbidden();
        $this->putJson("/api/clients/{$client->uuid}", ['name' => 'Y'])->assertForbidden();
        $this->deleteJson("/api/clients/{$client->uuid}")->assertForbidden();
    }
}
