<?php

namespace Tests\Feature\ApiToken;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth.api-token')->get('/api/testing/integration', function () {
            return response()->json(['tenant_uuid' => TenantContext::tenant()?->uuid]);
        });

        Route::middleware(['auth.api-token', 'tenant', 'permission:user.read'])->get('/api/testing/users-read', function () {
            return response()->json(['ok' => true]);
        });

        Route::middleware(['auth.api-token', 'tenant', 'permission:user.create'])->post('/api/testing/users-create', function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_admin_can_create_api_token_and_plain_text_is_shown_once(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->postJson('/api/api-tokens', ['name' => 'Integração ERP'])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'api_token' => ['id', 'name']]]);

        $plain = $response->json('data.token');

        $this->assertStringStartsWith(ApiToken::PREFIX, $plain);

        $this->assertDatabaseHas('api_tokens', [
            'tenant_id' => $tenant->getKey(),
            'name' => 'Integração ERP',
            'token_hash' => ApiToken::hash($plain),
        ]);

        $this->assertNull($response->json('data.api_token.token_hash'));
    }

    public function test_index_lists_only_tokens_of_the_current_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        ApiToken::factory()->count(2)->for($tenantA)->create();
        ApiToken::factory()->count(3)->for($tenantB)->create();

        Sanctum::actingAs($this->createAdmin($tenantA));

        $response = $this->getJson('/api/api-tokens')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_api_token_authenticates_integration_requests(): void
    {
        $tenant = $this->createTenantWithRoles();

        $plain = ApiToken::PREFIX.Str::random(48);
        $token = ApiToken::factory()->for($tenant)->create(['token_hash' => ApiToken::hash($plain)]);

        $this->forgetTenantContext();

        $this->getJson('/api/testing/integration', ['Authorization' => "Bearer {$plain}"])
            ->assertOk()
            ->assertJsonPath('tenant_uuid', $tenant->uuid);

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_invalid_expired_or_revoked_tokens_are_rejected(): void
    {
        $tenant = $this->createTenantWithRoles();

        $this->forgetTenantContext();
        $this->getJson('/api/testing/integration')->assertUnauthorized();

        $this->getJson('/api/testing/integration', ['Authorization' => 'Bearer api_invalido'])
            ->assertUnauthorized();

        $expired = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenant)->create([
            'token_hash' => ApiToken::hash($expired),
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/testing/integration', ['Authorization' => "Bearer {$expired}"])
            ->assertUnauthorized();

        $revoked = ApiToken::PREFIX.Str::random(48);
        $model = ApiToken::factory()->for($tenant)->create(['token_hash' => ApiToken::hash($revoked)]);

        Sanctum::actingAs($this->createAdmin($tenant));
        $this->deleteJson("/api/api-tokens/{$model->getKey()}")->assertOk();

        $this->app->get('auth')->forgetGuards();
        $this->forgetTenantContext();

        $this->getJson('/api/testing/integration', ['Authorization' => "Bearer {$revoked}"])
            ->assertUnauthorized();
    }

    public function test_cannot_revoke_tokens_of_other_tenants(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        $foreign = ApiToken::factory()->for($tenantB)->create();

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->deleteJson("/api/api-tokens/{$foreign->getKey()}")->assertNotFound();

        $this->assertDatabaseHas('api_tokens', ['id' => $foreign->getKey()]);
    }

    public function test_member_without_permission_cannot_manage_tokens(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMember($tenant));

        $this->getJson('/api/api-tokens')->assertForbidden();
        $this->postJson('/api/api-tokens', ['name' => 'X'])->assertForbidden();
    }

    public function test_custom_role_with_granular_token_permissions(): void
    {
        $tenant = $this->createTenantWithRoles();

        $role = Role::factory()->for($tenant)->create(['name' => 'Integrações']);
        $role->grantPermissions(Permission::API_TOKEN_READ, Permission::API_TOKEN_CREATE);

        $user = User::factory()->for($tenant)->create();
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson('/api/api-tokens')->assertOk();

        $created = $this->postJson('/api/api-tokens', ['name' => 'ERP'])
            ->assertCreated()
            ->json('data.api_token.id');

        $this->deleteJson("/api/api-tokens/{$created}")->assertForbidden();

        $role->grantPermissions(Permission::API_TOKEN_DELETE);
        $user->unsetRelation('roles');

        $this->deleteJson("/api/api-tokens/{$created}")->assertOk();

        $this->assertDatabaseMissing('api_tokens', ['id' => $created]);
    }

    public function test_token_with_scoped_permissions_can_access_allowed_routes_only(): void
    {
        $tenant = $this->createTenantWithRoles();

        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenant)->create([
            'token_hash' => ApiToken::hash($plain),
            'permissions' => [Permission::USER_READ->value],
        ]);

        $headers = ['Authorization' => "Bearer {$plain}"];

        $this->getJson('/api/testing/users-read', $headers)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/api/testing/users-create', [], $headers)
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Este token não possui escopo para executar esta ação.');
    }

    public function test_token_with_null_permissions_has_full_access(): void
    {
        $tenant = $this->createTenantWithRoles();

        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenant)->create([
            'token_hash' => ApiToken::hash($plain),
            'permissions' => null,
        ]);

        $headers = ['Authorization' => "Bearer {$plain}"];

        $this->getJson('/api/testing/users-read', $headers)->assertOk();
        $this->postJson('/api/testing/users-create', [], $headers)->assertOk();
    }

    public function test_token_with_empty_permissions_cannot_access_anything_scoped(): void
    {
        $tenant = $this->createTenantWithRoles();

        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenant)->create([
            'token_hash' => ApiToken::hash($plain),
            'permissions' => [],
        ]);

        $headers = ['Authorization' => "Bearer {$plain}"];

        $this->getJson('/api/testing/users-read', $headers)
            ->assertForbidden()
            ->assertJsonPath('message', 'Este token não possui escopo para executar esta ação.');
    }

    public function test_admin_can_create_token_with_specific_permissions(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->postJson('/api/api-tokens', [
            'name' => 'Token Leitura',
            'permissions' => [Permission::USER_READ->value, Permission::TENANT_READ->value],
        ])
            ->assertCreated()
            ->assertJsonPath('data.api_token.permissions', [Permission::USER_READ->value, Permission::TENANT_READ->value]);

        $plain = $response->json('data.token');

        $this->assertDatabaseHas('api_tokens', [
            'name' => 'Token Leitura',
            'token_hash' => ApiToken::hash($plain),
        ]);

        $headers = ['Authorization' => "Bearer {$plain}"];

        $this->getJson('/api/testing/users-read', $headers)->assertOk();
        $this->postJson('/api/testing/users-create', [], $headers)->assertForbidden();
    }

    public function test_token_permissions_must_be_valid_enums(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/api-tokens', [
            'name' => 'Token Inválido',
            'permissions' => ['permissao.inexistente'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['permissions.0']);
    }
}
