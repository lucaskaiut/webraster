<?php

namespace Tests\Feature\Tenant;

use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('tenant')->get('/api/testing/current-tenant', function () {
            return response()->json([
                'resolved' => TenantContext::isResolved(),
                'tenant_id' => TenantContext::tenantId(),
                'tenant_uuid' => TenantContext::tenant()?->uuid,
            ]);
        });
    }

    public function test_resolves_tenant_from_authenticated_user(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = $this->createMember($tenant);

        Sanctum::actingAs($user);

        $this->getJson('/api/testing/current-tenant')
            ->assertOk()
            ->assertJsonPath('resolved', true)
            ->assertJsonPath('tenant_uuid', $tenant->uuid);
    }

    public function test_resolves_tenant_from_api_token(): void
    {
        $tenant = $this->createTenantWithRoles();

        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenant)->create(['token_hash' => ApiToken::hash($plain)]);

        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant', ['Authorization' => "Bearer {$plain}"])
            ->assertOk()
            ->assertJsonPath('resolved', true)
            ->assertJsonPath('tenant_uuid', $tenant->uuid);
    }

    public function test_expired_api_token_does_not_resolve_tenant(): void
    {
        $tenant = $this->createTenantWithRoles();

        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenant)->create([
            'token_hash' => ApiToken::hash($plain),
            'expires_at' => now()->subMinute(),
        ]);

        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant', ['Authorization' => "Bearer {$plain}"])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_resolves_tenant_from_referer_header(): void
    {
        $tenant = $this->createTenantWithRoles(['domain' => 'cliente1.com.br']);

        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant', ['Referer' => 'https://cliente1.com.br/dashboard'])
            ->assertOk()
            ->assertJsonPath('tenant_uuid', $tenant->uuid);

        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant', ['Referer' => 'https://www.cliente1.com.br'])
            ->assertOk()
            ->assertJsonPath('tenant_uuid', $tenant->uuid);
    }

    public function test_authenticated_user_takes_precedence_over_referer(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'cliente2.com.br']);

        Sanctum::actingAs($this->createMember($tenantA));

        $this->getJson('/api/testing/current-tenant', ['Referer' => 'https://cliente2.com.br'])
            ->assertOk()
            ->assertJsonPath('tenant_uuid', $tenantA->uuid);
    }

    public function test_unknown_referer_returns_404(): void
    {
        $this->createTenantWithRoles(['domain' => 'cliente1.com.br']);

        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant', ['Referer' => 'https://desconhecido.com.br'])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Tenant não encontrado para a requisição atual.');
    }

    public function test_unresolvable_request_returns_404(): void
    {
        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
