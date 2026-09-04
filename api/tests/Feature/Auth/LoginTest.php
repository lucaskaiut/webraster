<?php

namespace Tests\Feature\Auth;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_login_returns_token_user_and_tenant(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@empresa.com')
            ->assertJsonPath('data.tenant.id', $tenant->uuid)
            ->assertJsonStructure(['data' => ['token', 'token_type']]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->postJson('/api/auth/login', [
            'email' => 'nao-existe@empresa.com',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_me_returns_user_tenant_roles_and_permissions(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'password',
        ])->json('data.token');

        $response = $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@empresa.com')
            ->assertJsonPath('data.tenant.id', $tenant->uuid)
            ->assertJsonPath('data.roles.0.name', DefaultRole::ADMINISTRATOR->value);

        $permissions = $response->json('data.permissions');

        $this->assertEqualsCanonicalizing(Permission::values(), $permissions);
    }

    public function test_logout_revokes_current_token(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'password',
        ])->json('data.token');

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/api/auth/logout', [], $headers)->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app->get('auth')->forgetGuards();

        $this->getJson('/api/auth/me', $headers)
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_unauthenticated_request_receives_401(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_soft_deleted_user_cannot_authenticate(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $user->delete();

        $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'password',
        ])->assertUnprocessable();
    }
}
