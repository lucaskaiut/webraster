<?php

namespace Tests\Feature\Auth;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'tenant' => [
                'name' => 'Empresa Exemplo',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@empresa.com',
                'phone' => '41999999999',
                'domain' => 'empresa.com.br',
            ],
            'user' => [
                'name' => 'Administrador',
                'email' => 'admin@empresa.com',
                'phone' => '41999999999',
                'document' => '529.982.247-25',
                'password' => '12345678',
            ],
        ], $overrides);
    }

    public function test_register_creates_tenant_admin_user_default_roles_and_token(): void
    {
        $response = $this->postJson('/api/auth/register', $this->payload());

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.tenant.domain', 'empresa.com.br')
            ->assertJsonPath('data.user.email', 'admin@empresa.com')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id'], 'tenant' => ['id']]]);

        $this->assertDatabaseHas('tenants', [
            'domain' => 'empresa.com.br',
            'document' => '11222333000181',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@empresa.com',
            'document' => '52998224725',
        ]);

        $tenantId = $this->getTenantId();

        $this->assertDatabaseHas('roles', ['tenant_id' => $tenantId, 'name' => DefaultRole::ADMINISTRATOR->value]);
        $this->assertDatabaseHas('roles', ['tenant_id' => $tenantId, 'name' => DefaultRole::USER->value]);
        $this->assertDatabaseCount('role_permissions', count(Permission::cases()) + 1);
        $this->assertDatabaseCount('user_roles', 1);

        $token = $response->json('data.token');

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@empresa.com')
            ->assertJsonPath('data.roles.0.name', DefaultRole::ADMINISTRATOR->value);
    }

    public function test_register_hashes_the_password(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $password = DB::table('users')
            ->where('email', 'admin@empresa.com')
            ->value('password');

        $this->assertNotSame('12345678', $password);
        $this->assertTrue(Hash::check('12345678', $password));
    }

    public function test_register_rejects_invalid_documents(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'tenant' => ['document' => '11111111000111'],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['tenant.document']);

        $this->postJson('/api/auth/register', $this->payload([
            'user' => ['document' => '11111111111'],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['user.document']);
    }

    public function test_register_rejects_duplicated_domain_and_emails(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/auth/register', $this->payload([
            'tenant' => ['email' => 'outro@empresa.com'],
            'user' => ['email' => 'outro-admin@empresa.com'],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['tenant.domain']);

        $this->postJson('/api/auth/register', $this->payload([
            'tenant' => ['domain' => 'outra.com.br', 'email' => 'outro@empresa.com'],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['user.email']);
    }

    public function test_first_tenant_is_root_and_next_tenants_use_it_as_parent(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $firstTenantId = $this->getTenantId();

        $this->assertDatabaseHas('tenants', [
            'id' => $firstTenantId,
            'parent_id' => null,
        ]);

        $this->postJson('/api/auth/register', $this->payload([
            'tenant' => [
                'name' => 'Filial',
                'document' => '04.252.011/0001-10',
                'email' => 'contato@filial.com',
                'domain' => 'filial.com.br',
            ],
            'user' => [
                'email' => 'admin@filial.com',
            ],
        ]))->assertCreated();

        $this->assertDatabaseHas('tenants', [
            'domain' => 'filial.com.br',
            'parent_id' => $firstTenantId,
        ]);
    }

    public function test_error_response_is_standardized(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonPath('success', false);
    }

    private function getTenantId(): int
    {
        return (int) DB::table('tenants')
            ->where('domain', 'empresa.com.br')
            ->value('id');
    }
}
