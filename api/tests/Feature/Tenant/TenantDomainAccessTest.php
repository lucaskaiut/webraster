<?php

namespace Tests\Feature\Tenant;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantDomainAccessTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Tenant $umbrella;

    private Tenant $childA;

    private Tenant $childB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenant_domains.base_domain' => 'nuvemax.srv.br']);

        $this->umbrella = $this->createTenantWithRoles(['name' => 'Grupo']);
        $this->childA = $this->createChildTenant($this->umbrella, [
            'name' => 'Empresa A', 'identifier' => 'empresa-a', 'domain' => 'empresa-a.nuvemax.srv.br',
        ]);
        $this->childB = $this->createChildTenant($this->umbrella, [
            'name' => 'Empresa B', 'identifier' => 'empresa-b', 'domain' => 'empresa-b.nuvemax.srv.br',
        ]);
    }

    public function test_user_accesses_own_tenant_through_its_domain(): void
    {
        Sanctum::actingAs($this->createAdmin($this->childA));

        $this->getJson('/api/auth/me', $this->originOf('empresa-a'))
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $this->childA->uuid);
    }

    public function test_user_cannot_access_another_tenant_domain(): void
    {
        Sanctum::actingAs($this->createAdmin($this->childA));

        $this->getJson('/api/auth/me', $this->originOf('empresa-b'))
            ->assertForbidden()
            ->assertJsonStructure(['errors' => ['tenant_domain_mismatch']]);

        $this->getJson('/api/tenant', $this->originOf('empresa-b'))->assertForbidden();
    }

    public function test_referer_identifies_domain_without_origin(): void
    {
        Sanctum::actingAs($this->createAdmin($this->childA));

        $this->getJson('/api/auth/me', ['Referer' => 'https://empresa-b.nuvemax.srv.br/veiculos'])
            ->assertForbidden();
    }

    public function test_umbrella_user_cannot_use_child_domain(): void
    {
        Sanctum::actingAs($this->createAdmin($this->umbrella));

        $this->getJson('/api/auth/me', $this->originOf('empresa-a'))->assertForbidden();
    }

    public function test_requests_without_tenant_domain_are_unchanged(): void
    {
        Sanctum::actingAs($this->createAdmin($this->childA));

        $this->getJson('/api/auth/me')->assertOk();
        $this->getJson('/api/auth/me', ['Origin' => 'https://agendamento.nuvemax.srv.br'])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $this->childA->uuid);
    }

    public function test_child_user_cannot_use_umbrella_domain(): void
    {
        $this->runPanelDomainMigration();

        $this->createAdmin($this->childA, ['email' => 'a@empresa.com']);

        $this->postJson('/api/auth/login', ['email' => 'a@empresa.com', 'password' => 'password'], $this->originOf('dash-tracker'))
            ->assertUnprocessable();

        Sanctum::actingAs($this->createAdmin($this->childA));

        $this->getJson('/api/auth/me', $this->originOf('dash-tracker'))
            ->assertForbidden()
            ->assertJsonStructure(['errors' => ['tenant_domain_mismatch']]);
    }

    public function test_umbrella_users_use_umbrella_domain(): void
    {
        $this->runPanelDomainMigration();

        $this->createAdmin($this->umbrella, ['email' => 'admin@grupo.com']);

        $this->postJson('/api/auth/login', ['email' => 'admin@grupo.com', 'password' => 'password'], $this->originOf('dash-tracker'))
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $this->umbrella->uuid);
    }

    public function test_master_switches_children_on_own_domain(): void
    {
        $this->runPanelDomainMigration();

        Sanctum::actingAs($this->createMaster($this->umbrella));

        $response = $this->getJson('/api/auth/me', [
            ...$this->originOf('dash-tracker'),
            'X-Tenant-Id' => $this->childB->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $this->childB->uuid);

        $this->assertCount(3, $response->json('data.available_tenants'));

        $this->postJson('/api/auth/select-tenant', ['tenant_id' => $this->childA->uuid], $this->originOf('dash-tracker'))
            ->assertOk();
    }

    public function test_master_is_locked_to_domain_tenant(): void
    {
        Sanctum::actingAs($this->createMaster($this->umbrella));

        $this->getJson('/api/auth/me', [
            ...$this->originOf('empresa-a'),
            'X-Tenant-Id' => $this->childB->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $this->childA->uuid)
            ->assertJsonPath('data.available_tenants', []);

        $this->postJson('/api/auth/select-tenant', ['tenant_id' => $this->childB->uuid], $this->originOf('empresa-a'))
            ->assertForbidden();
    }

    public function test_master_of_another_group_cannot_use_domain(): void
    {
        $other = $this->createTenantWithRoles(['name' => 'Outro Grupo']);
        Sanctum::actingAs($this->createMaster($other));

        $this->getJson('/api/auth/me', $this->originOf('empresa-a'))->assertForbidden();
    }

    public function test_login_on_domain_accepts_only_users_of_that_tenant(): void
    {
        $this->createAdmin($this->childA, ['email' => 'a@empresa.com']);
        $this->createAdmin($this->childB, ['email' => 'b@empresa.com']);

        $this->postJson('/api/auth/login', ['email' => 'b@empresa.com', 'password' => 'password'], $this->originOf('empresa-a'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/login', ['email' => 'a@empresa.com', 'password' => 'password'], $this->originOf('empresa-a'))
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $this->childA->uuid);
    }

    public function test_login_rejects_identifier_of_another_tenant(): void
    {
        $this->createAdmin($this->childA, ['email' => 'a@empresa.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'a@empresa.com',
            'password' => 'password',
            'tenant_identifier' => 'empresa-b',
        ], $this->originOf('empresa-a'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_identifier');
    }

    public function test_master_logs_in_on_child_domain(): void
    {
        $this->createMaster($this->umbrella, ['email' => 'master@grupo.com']);

        $this->postJson('/api/auth/login', ['email' => 'master@grupo.com', 'password' => 'password'], $this->originOf('empresa-a'))
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $this->childA->uuid);
    }

    public function test_webraster_tenant_owns_main_panel_domain(): void
    {
        $webraster = $this->createChildTenant($this->umbrella, ['name' => 'Web Raster', 'identifier' => 'webraster']);
        $this->runPanelDomainMigration();

        $this->assertSame('webraster.nuvemax.srv.br', $webraster->refresh()->domain);
        $this->assertSame('dash-tracker.nuvemax.srv.br', $this->umbrella->refresh()->domain);

        $this->createAdmin($webraster, ['email' => 'w@webraster.com']);
        $this->createAdmin($this->childA, ['email' => 'a@empresa.com']);

        $this->postJson('/api/auth/login', ['email' => 'w@webraster.com', 'password' => 'password'], $this->originOf('webraster'))
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $webraster->uuid);

        $this->postJson('/api/auth/login', ['email' => 'a@empresa.com', 'password' => 'password'], $this->originOf('webraster'))
            ->assertUnprocessable();
    }

    public function test_command_assigns_explicit_domain(): void
    {
        config([
            'tenant_domains.enabled' => true,
            'services.vercel.token' => 'v', 'services.vercel.project' => 'webraster',
            'services.vercel.base_url' => 'https://api.vercel.com',
            'services.cloudflare.token' => 'c', 'services.cloudflare.zone_id' => 'z',
            'services.cloudflare.base_url' => 'https://api.cloudflare.com/client/v4',
        ]);
        Http::fake([
            'api.vercel.com/v6/*' => Http::response(['recommendedCNAME' => [['rank' => 1, 'value' => 'abc.vercel-dns-017.com.']]]),
            'api.vercel.com/*' => Http::response(['verified' => true]),
            'api.cloudflare.com/*' => Http::response(['success' => true, 'result' => [[
                'id' => 'r1', 'type' => 'CNAME', 'name' => 'webraster.nuvemax.srv.br', 'content' => 'abc.vercel-dns-017.com', 'proxied' => false,
            ]]]),
        ]);

        $tenant = $this->createChildTenant($this->umbrella, ['identifier' => 'web-raster']);

        $this->artisan('tenants:provision-domain', ['tenants' => ['web-raster'], '--domain' => 'webraster.nuvemax.srv.br'])
            ->assertSuccessful();

        $this->assertSame('webraster.nuvemax.srv.br', $tenant->refresh()->domain);

        $this->artisan('tenants:provision-domain', ['tenants' => ['empresa-b'], '--domain' => 'webraster.nuvemax.srv.br'])
            ->assertFailed();
        $this->artisan('tenants:provision-domain', ['tenants' => ['empresa-b'], '--domain' => 'x.outro.com'])
            ->assertExitCode(2);
    }

    public function test_public_branding_by_domain(): void
    {
        $this->childA->forceFill(['theme' => ['primary_color' => '#112233']])->save();

        $this->getJson('/api/public/domains/EMPRESA-A.nuvemax.srv.br')
            ->assertOk()
            ->assertJsonPath('data.id', $this->childA->uuid)
            ->assertJsonPath('data.theme.primary_color', '#112233')
            ->assertJsonMissingPath('data.email');

        $this->getJson('/api/public/domains/nao-existe.nuvemax.srv.br')->assertNotFound();
        $this->getJson('/api/public/domains/empresa-a.outro.com')->assertNotFound();
    }

    private function runPanelDomainMigration(): void
    {
        (require database_path('migrations/2026_11_09_000002_assign_panel_domains.php'))->up();
    }

    /**
     * @return array<string, string>
     */
    private function originOf(string $label): array
    {
        return ['Origin' => "https://{$label}.nuvemax.srv.br"];
    }
}
