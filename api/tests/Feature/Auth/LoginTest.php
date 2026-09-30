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
        $tenant->update([
            'app_name' => 'Rastreio Fácil',
            'app_logo_path' => 'uploads/app-logo.png',
            'app_primary_color' => '#5B5CE2',
            'app_secondary_color' => '#0EA5E9',
        ]);
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
            ->assertJsonPath('data.tenant.app_name', 'Rastreio Fácil')
            ->assertJsonPath('data.tenant.app_logo_url', asset('storage/uploads/app-logo.png'))
            ->assertJsonPath('data.tenant.app_primary_color', '#5B5CE2')
            ->assertJsonPath('data.tenant.app_secondary_color', '#0EA5E9')
            ->assertJsonStructure(['data' => ['token', 'token_type']]);
    }

    public function test_login_with_tenant_identifier_selects_the_tenant_user(): void
    {
        $tenantA = $this->createTenantWithRoles(['identifier' => 'empresa-a']);
        $tenantB = $this->createTenantWithRoles(['identifier' => 'empresa-b']);

        $this->createAdmin($tenantA, ['email' => 'shared@empresa.com', 'password' => 'password-a']);
        $this->createAdmin($tenantB, ['email' => 'shared@empresa.com', 'password' => 'password-b']);

        $this->postJson('/api/auth/login', [
            'email' => 'shared@empresa.com',
            'password' => 'password-a',
            'tenant_identifier' => 'empresa-a',
        ])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $tenantA->uuid);

        $this->postJson('/api/auth/login', [
            'email' => 'shared@empresa.com',
            'password' => 'password-b',
            'tenant_identifier' => 'empresa-b',
        ])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $tenantB->uuid);

        $this->postJson('/api/auth/login', [
            'email' => 'shared@empresa.com',
            'password' => 'password-b',
            'tenant_identifier' => 'empresa-a',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_unknown_tenant_identifier(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'password',
            'tenant_identifier' => 'nao-existe',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_identifier']);
    }

    public function test_master_login_with_child_identifier_binds_tenant_to_token(): void
    {
        $umbrella = $this->createTenantWithRoles(['identifier' => 'grupo-central']);
        $child = $this->createChildTenant($umbrella, ['identifier' => 'filial-sul']);
        $this->createMaster($umbrella, ['email' => 'master@empresa.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'master@empresa.com',
            'password' => 'password',
            'tenant_identifier' => 'filial-sul',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $child->uuid);

        $token = $response->json('data.token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tenant_id' => $child->getKey(),
        ]);

        // Em produção cada requisição tem um contexto novo; no teste descartamos
        // o tenant definido no login para simular a próxima requisição.
        $this->forgetTenantContext();

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $child->uuid);
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
