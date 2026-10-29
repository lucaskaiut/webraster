<?php

namespace Tests\Feature\ACL;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_login_is_denied_on_panel_the_user_cannot_access(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createSchedulingOnlyUser($tenant, ['email' => 'agenda@empresa.com']);

        $this->withHeader('X-Panel', 'web')
            ->postJson('/api/auth/login', [
                'email' => 'agenda@empresa.com',
                'password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Seu usuário não tem acesso a este painel.');

        $this->assertGuest('web');
    }

    public function test_login_is_allowed_on_panel_the_user_can_access(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createSchedulingOnlyUser($tenant, ['email' => 'agenda@empresa.com']);

        $this->withHeader('X-Panel', 'agendamento')
            ->postJson('/api/auth/login', [
                'email' => 'agenda@empresa.com',
                'password' => 'password',
            ])
            ->assertOk();
    }

    public function test_invalid_credentials_still_return_validation_error_on_restricted_panel(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createSchedulingOnlyUser($tenant, ['email' => 'agenda@empresa.com']);

        $this->withHeader('X-Panel', 'web')
            ->postJson('/api/auth/login', [
                'email' => 'agenda@empresa.com',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_requests_are_denied_on_panel_the_user_cannot_access(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createSchedulingOnlyUser($tenant));

        $this->withHeader('X-Panel', 'web')
            ->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Seu usuário não tem acesso a este painel.');

        $this->withHeader('X-Panel', 'agendamento')
            ->getJson('/api/auth/me')
            ->assertOk();
    }

    public function test_session_authenticated_requests_are_denied_on_panel_the_user_cannot_access(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->actingAs($this->createSchedulingOnlyUser($tenant), 'web');

        $this->withHeader('X-Panel', 'web')
            ->getJson('/api/auth/me')
            ->assertForbidden();
    }

    public function test_requests_without_panel_header_are_not_restricted(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createSchedulingOnlyUser($tenant));

        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_default_roles_keep_access_to_their_panels(): void
    {
        $tenant = $this->createTenantWithRoles();

        $this->assertTrue($this->createAdmin($tenant)->hasPermission(Permission::PANEL_WEB_ACCESS));
        $this->assertTrue($this->createAdmin($tenant)->hasPermission(Permission::PANEL_SCHEDULING_ACCESS));
        $this->assertTrue($this->createMember($tenant)->hasPermission(Permission::PANEL_WEB_ACCESS));
        $this->assertTrue($this->createMember($tenant)->hasPermission(Permission::PANEL_SCHEDULING_ACCESS));
        $this->assertTrue($this->createClient($tenant)->hasPermission(Permission::PANEL_WEB_ACCESS));
        $this->assertFalse($this->createClient($tenant)->hasPermission(Permission::PANEL_SCHEDULING_ACCESS));
    }

    private function createSchedulingOnlyUser(Tenant $tenant, array $attributes = []): User
    {
        $role = $this->roleFor($tenant, DefaultRole::OPERATOR);
        $role->revokePermissions(Permission::PANEL_WEB_ACCESS);

        $user = User::factory()->for($tenant)->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
