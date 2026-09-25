<?php

namespace Tests\Feature\Client;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
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
        $other = $this->createChildTenant($umbrella);

        Client::factory()->for($tenant)->create(['name' => 'Cliente A']);
        Client::factory()->for($other)->create(['name' => 'Cliente B']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/clients')->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Cliente A'));
        $this->assertFalse($names->contains('Cliente B'));
    }

    public function test_index_can_filter_by_delinquency(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();

        $overdue = Client::factory()->for($tenant)->create(['name' => 'Cliente Inadimplente']);
        $current = Client::factory()->for($tenant)->create(['name' => 'Cliente Em Dia']);

        $overdueSubscription = FinanceSubscription::factory()->forClient($overdue, $plan)->create();
        $currentSubscription = FinanceSubscription::factory()->forClient($current, $plan)->create();

        FinanceBilling::factory()->forSubscription($overdueSubscription)->create([
            'number' => 1,
            'status' => BillingStatus::OVERDUE,
        ]);
        FinanceBilling::factory()->forSubscription($currentSubscription)->create([
            'number' => 2,
            'status' => BillingStatus::PAID,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $delinquentNames = collect($this->getJson('/api/clients?delinquent=1')->assertOk()->json('data'))
            ->pluck('name');
        $this->assertTrue($delinquentNames->contains('Cliente Inadimplente'));
        $this->assertFalse($delinquentNames->contains('Cliente Em Dia'));

        $currentNames = collect($this->getJson('/api/clients?delinquent=0')->assertOk()->json('data'))
            ->pluck('name');
        $this->assertTrue($currentNames->contains('Cliente Em Dia'));
        $this->assertFalse($currentNames->contains('Cliente Inadimplente'));
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

    public function test_store_client_user_without_password_generates_from_client_document(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create(['document' => '12345678901']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/clients/{$client->uuid}/users", [
            'name' => 'Portal Cliente',
            'email' => 'portal@cliente.com',
            'document' => '52998224725',
        ])->assertCreated();

        $user = \App\Modules\User\Models\User::query()
            ->where('client_id', $client->getKey())
            ->where('email', 'portal@cliente.com')
            ->firstOrFail();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('123456', $user->password));
    }

    public function test_store_rejects_duplicate_document_of_active_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $document = Document::fakeCnpj();

        Client::factory()->for($tenant)->create(['document' => $document]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/clients', [
            'name' => 'Duplicado',
            'document' => $document,
        ])->assertUnprocessable()->assertJsonValidationErrors('document');
    }

    public function test_store_allows_reusing_document_of_soft_deleted_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $document = Document::fakeCnpj();

        $deleted = Client::factory()->for($tenant)->create(['document' => $document]);
        $deleted->delete();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/clients', [
            'name' => 'Cliente Reativado',
            'document' => $document,
        ])->assertCreated();

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $tenant->getKey(),
            'document' => $document,
            'name' => 'Cliente Reativado',
        ]);
    }

    public function test_deleting_client_cascades_to_linked_users(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $roleId = $this->roleFor($tenant, DefaultRole::CLIENT)->getKey();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/clients/{$client->uuid}/users", [
            'name' => 'Portal Cliente',
            'email' => 'portal@cliente.com',
            'password' => '12345678',
            'role_ids' => [$roleId],
        ])->assertCreated();

        $this->deleteJson("/api/clients/{$client->uuid}")->assertOk();

        $this->assertSoftDeleted('clients', ['id' => $client->getKey()]);
        $this->assertSoftDeleted('users', ['email' => 'portal@cliente.com']);
    }

    public function test_store_allows_reusing_email_of_soft_deleted_user(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $roleId = $this->roleFor($tenant, DefaultRole::CLIENT)->getKey();

        Sanctum::actingAs($this->createAdmin($tenant));

        $first = Client::factory()->for($tenant)->create();
        $this->postJson("/api/clients/{$first->uuid}/users", [
            'name' => 'Portal Cliente',
            'email' => 'portal@cliente.com',
            'password' => '12345678',
            'role_ids' => [$roleId],
        ])->assertCreated();

        $this->deleteJson("/api/clients/{$first->uuid}")->assertOk();

        $second = Client::factory()->for($tenant)->create();
        $this->postJson("/api/clients/{$second->uuid}/users", [
            'name' => 'Portal Cliente Novo',
            'email' => 'portal@cliente.com',
            'password' => '12345678',
            'role_ids' => [$roleId],
        ])->assertCreated();
    }

    public function test_cannot_access_client_of_other_tenant(): void
    {
        [$umbrella, $tenantA] = $this->createOperationalChild();
        $tenantB = $this->createChildTenant($umbrella);
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
