<?php

namespace Tests\Feature\ACL;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class RoleCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_index_lists_only_roles_of_the_current_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        Role::factory()->for($tenantA)->create(['name' => 'Suporte']);
        Role::factory()->for($tenantB)->create(['name' => 'Financeiro']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $response = $this->getJson('/api/roles')->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertSame(3, $response->json('meta.total'));
        $this->assertTrue($names->contains('Suporte'));
        $this->assertFalse($names->contains('Financeiro'));
    }

    public function test_store_creates_role_with_permissions_for_current_tenant(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/roles', [
            'name' => 'Suporte',
            'description' => 'Atendimento ao cliente',
            'permissions' => [Permission::USER_READ->value, Permission::USER_UPDATE->value],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Suporte')
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('roles', ['tenant_id' => $tenant->getKey(), 'name' => 'Suporte']);

        $role = Role::query()->forTenant($tenant)->where('name', 'Suporte')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [Permission::USER_READ->value, Permission::USER_UPDATE->value],
            $role->permissionValues()->all(),
        );
    }

    public function test_store_rejects_unknown_permissions_and_duplicated_name(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/roles', [
            'name' => 'Suporte',
            'permissions' => ['permissao.inexistente'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['permissions.0']);

        $this->postJson('/api/roles', ['name' => DefaultRole::ADMINISTRATOR->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_same_role_name_is_allowed_in_different_tenants(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        Role::factory()->for($tenantB)->create(['name' => 'Suporte']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->postJson('/api/roles', ['name' => 'Suporte'])->assertCreated();
    }

    public function test_show_returns_role_and_404_for_other_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        $mine = Role::factory()->for($tenantA)->create(['name' => 'Suporte']);
        $foreign = Role::factory()->for($tenantB)->create(['name' => 'Financeiro']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->getJson("/api/roles/{$mine->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Suporte');

        $this->getJson("/api/roles/{$foreign->getKey()}")->assertNotFound();
    }

    public function test_update_syncs_permissions(): void
    {
        $tenant = $this->createTenantWithRoles();
        $role = Role::factory()->for($tenant)->create(['name' => 'Suporte']);
        $role->grantPermissions(Permission::USER_READ, Permission::USER_UPDATE);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/roles/{$role->getKey()}", [
            'name' => 'Suporte N2',
            'permissions' => [Permission::USER_READ->value, Permission::ROLE_READ->value],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Suporte N2');

        $this->assertEqualsCanonicalizing(
            [Permission::USER_READ->value, Permission::ROLE_READ->value],
            $role->refresh()->permissionValues()->all(),
        );
    }

    public function test_destroy_deletes_role_and_detaches_users(): void
    {
        $tenant = $this->createTenantWithRoles();
        $role = Role::factory()->for($tenant)->create(['name' => 'Suporte']);
        $role->grantPermissions(Permission::USER_READ);

        $user = User::factory()->for($tenant)->create();
        $user->assignRole($role);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->deleteJson("/api/roles/{$role->getKey()}")->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->getKey()]);
        $this->assertDatabaseMissing('user_roles', ['role_id' => $role->getKey()]);
        $this->assertFalse($user->fresh()->hasPermission(Permission::USER_READ));
    }

    public function test_default_roles_can_be_updated_and_deleted(): void
    {
        $tenant = $this->createTenantWithRoles();
        $userRole = $this->roleFor($tenant, DefaultRole::USER);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/roles/{$userRole->getKey()}", [
            'name' => 'Usuário Renomeado',
            'permissions' => [Permission::USER_READ->value],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Usuário Renomeado');

        $this->deleteJson("/api/roles/{$userRole->getKey()}")->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $userRole->getKey()]);
    }

    public function test_update_cannot_reach_roles_of_other_tenants(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        $foreign = Role::factory()->for($tenantB)->create(['name' => 'Financeiro']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->putJson("/api/roles/{$foreign->getKey()}", ['name' => 'Invadido'])->assertNotFound();
        $this->deleteJson("/api/roles/{$foreign->getKey()}")->assertNotFound();

        $this->assertDatabaseHas('roles', ['id' => $foreign->getKey(), 'name' => 'Financeiro']);
    }

    public function test_member_without_permission_cannot_manage_roles(): void
    {
        $tenant = $this->createTenantWithRoles();
        $role = Role::factory()->for($tenant)->create(['name' => 'Suporte']);

        Sanctum::actingAs($this->createMember($tenant));

        $this->getJson('/api/roles')->assertForbidden();
        $this->postJson('/api/roles', ['name' => 'X'])->assertForbidden();
        $this->putJson("/api/roles/{$role->getKey()}", ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/roles/{$role->getKey()}")->assertForbidden();
    }

    public function test_custom_role_with_role_read_only_can_list_but_not_mutate(): void
    {
        $tenant = $this->createTenantWithRoles();

        $reader = Role::factory()->for($tenant)->create(['name' => 'Leitor de Roles']);
        $reader->grantPermissions(Permission::ROLE_READ);

        $user = User::factory()->for($tenant)->create();
        $user->assignRole($reader);

        Sanctum::actingAs($user);

        $this->getJson('/api/roles')->assertOk();
        $this->postJson('/api/roles', ['name' => 'Nova'])->assertForbidden();
    }
}
