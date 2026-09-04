<?php

namespace Tests\Feature\User;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_index_lists_only_users_of_the_current_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        $admin = $this->createAdmin($tenantA);
        User::factory()->count(2)->for($tenantA)->create();
        User::factory()->count(3)->for($tenantB)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users')->assertOk();

        $this->assertSame(3, $response->json('meta.total'));

        $emails = collect($response->json('data'))->pluck('email');
        $foreignEmails = User::query()->forTenant($tenantB)->pluck('email');

        $this->assertTrue($emails->intersect($foreignEmails)->isEmpty());
    }

    public function test_show_returns_user_of_same_tenant_and_404_for_other_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        $admin = $this->createAdmin($tenantA);
        $mine = User::factory()->for($tenantA)->create();
        $foreign = User::factory()->for($tenantB)->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/users/{$mine->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->uuid);

        $this->getJson("/api/users/{$foreign->uuid}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_store_creates_user_bound_to_the_current_tenant(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $userRoleId = $this->roleFor($tenant, DefaultRole::USER)->getKey();

        $this->postJson('/api/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@empresa.com',
            'phone' => '41988887777',
            'document' => '529.982.247-25',
            'password' => '12345678',
            'role_ids' => [$userRoleId],
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'novo@empresa.com')
            ->assertJsonPath('data.document', '52998224725')
            ->assertJsonPath('data.roles.0.name', DefaultRole::USER->value);

        $this->assertDatabaseHas('users', [
            'email' => 'novo@empresa.com',
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function test_store_requires_at_least_one_role_from_current_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $foreignRoleId = $this->roleFor($tenantB, DefaultRole::USER)->getKey();

        $this->postJson('/api/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@empresa.com',
            'password' => '12345678',
            'role_ids' => [$foreignRoleId],
        ])->assertUnprocessable()->assertJsonValidationErrors(['role_ids.0']);

        $this->postJson('/api/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@empresa.com',
            'password' => '12345678',
        ])->assertUnprocessable()->assertJsonValidationErrors(['role_ids']);
    }

    public function test_store_validates_email_uniqueness_and_cpf(): void
    {
        $tenant = $this->createTenantWithRoles();
        $admin = $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'X',
            'email' => 'admin@empresa.com',
            'password' => '12345678',
            'role_ids' => [$this->roleFor($tenant, DefaultRole::USER)->getKey()],
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->postJson('/api/users', [
            'name' => 'X',
            'email' => 'x@empresa.com',
            'document' => '123',
            'password' => '12345678',
            'role_ids' => [$this->roleFor($tenant, DefaultRole::USER)->getKey()],
        ])->assertUnprocessable()->assertJsonValidationErrors(['document']);
    }

    public function test_update_changes_user_data_and_roles(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = User::factory()->for($tenant)->create();
        $user->assignRole($this->roleFor($tenant, DefaultRole::USER));

        Sanctum::actingAs($this->createAdmin($tenant));

        $adminRoleId = $this->roleFor($tenant, DefaultRole::ADMINISTRATOR)->getKey();

        $this->putJson("/api/users/{$user->uuid}", [
            'name' => 'Renomeado',
            'role_ids' => [$adminRoleId],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renomeado')
            ->assertJsonPath('data.roles.0.name', DefaultRole::ADMINISTRATOR->value);

        $this->assertDatabaseHas('users', ['id' => $user->getKey(), 'name' => 'Renomeado']);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->getKey(),
            'role_id' => $adminRoleId,
        ]);
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $user->getKey(),
            'role_id' => $this->roleFor($tenant, DefaultRole::USER)->getKey(),
        ]);
    }

    public function test_update_cannot_reach_users_of_other_tenants(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);
        $foreign = User::factory()->for($tenantB)->create();

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->putJson("/api/users/{$foreign->uuid}", ['name' => 'Invadido'])
            ->assertNotFound();

        $this->assertDatabaseMissing('users', ['id' => $foreign->getKey(), 'name' => 'Invadido']);
    }

    public function test_destroy_soft_deletes_user_and_revokes_tokens(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = User::factory()->for($tenant)->create();
        $user->createToken('auth_token');

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->deleteJson("/api/users/{$user->uuid}")->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_cannot_delete_itself(): void
    {
        $tenant = $this->createTenantWithRoles();
        $admin = $this->createAdmin($tenant);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$admin->uuid}")->assertForbidden();
    }

    public function test_member_cannot_create_update_or_delete_users(): void
    {
        $tenant = $this->createTenantWithRoles();
        $target = User::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createMember($tenant));

        $this->postJson('/api/users', [])->assertForbidden();
        $this->putJson("/api/users/{$target->uuid}", ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/users/{$target->uuid}")->assertForbidden();
    }
}
