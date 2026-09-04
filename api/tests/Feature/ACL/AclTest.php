<?php

namespace Tests\Feature\ACL;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AclTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_default_roles_are_created_with_expected_permissions(): void
    {
        $tenant = $this->createTenantWithRoles();

        $admin = $this->roleFor($tenant, DefaultRole::ADMINISTRATOR);
        $member = $this->roleFor($tenant, DefaultRole::USER);

        $this->assertEqualsCanonicalizing(
            Permission::values(),
            $admin->permissionValues()->all(),
        );

        $this->assertSame([Permission::USER_READ->value], $member->permissionValues()->all());
    }

    public function test_user_role_and_permission_helpers(): void
    {
        $tenant = $this->createTenantWithRoles();
        $admin = $this->createAdmin($tenant);
        $member = $this->createMember($tenant);

        $this->assertTrue($admin->hasRole(DefaultRole::ADMINISTRATOR->value));
        $this->assertFalse($admin->hasRole(DefaultRole::USER->value));

        $this->assertTrue($admin->hasPermission(Permission::USER_DELETE));
        $this->assertTrue($admin->hasPermission('tenant.update'));

        $this->assertTrue($member->hasPermission(Permission::USER_READ));
        $this->assertFalse($member->hasPermission(Permission::USER_CREATE));
        $this->assertFalse($member->hasPermission('permissao.inexistente'));
    }

    public function test_role_permission_sync_grant_and_revoke(): void
    {
        $tenant = $this->createTenantWithRoles();
        $role = Role::factory()->for($tenant)->create(['name' => 'Suporte']);

        $role->grantPermissions(Permission::USER_READ, Permission::USER_UPDATE);
        $this->assertEqualsCanonicalizing(
            [Permission::USER_READ->value, Permission::USER_UPDATE->value],
            $role->permissionValues()->all(),
        );

        $role->grantPermissions(Permission::USER_READ);
        $this->assertCount(2, $role->refresh()->permissionValues());

        $role->revokePermissions(Permission::USER_UPDATE);
        $this->assertSame([Permission::USER_READ->value], $role->refresh()->permissionValues()->all());

        $role->syncPermissions([Permission::TENANT_READ]);
        $this->assertSame([Permission::TENANT_READ->value], $role->refresh()->permissionValues()->all());
    }

    public function test_assigning_and_removing_roles_changes_permissions(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = $this->createMember($tenant);

        $this->assertFalse($user->hasPermission(Permission::USER_CREATE));

        $user->assignRole($this->roleFor($tenant, DefaultRole::ADMINISTRATOR));
        $this->assertTrue($user->hasPermission(Permission::USER_CREATE));

        $user->removeRole($this->roleFor($tenant, DefaultRole::ADMINISTRATOR));
        $this->assertFalse($user->fresh()->hasPermission(Permission::USER_CREATE));
    }

    public function test_permission_middleware_blocks_users_without_permission(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMember($tenant));

        $this->getJson('/api/users')->assertOk();

        $this->postJson('/api/users', [])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Você não possui permissão para executar esta ação.');
    }

    public function test_permission_middleware_allows_users_with_permission(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@empresa.com',
            'password' => '12345678',
            'role_ids' => [$this->roleFor($tenant, DefaultRole::USER)->getKey()],
        ])->assertCreated();
    }
}
