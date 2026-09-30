<?php

namespace Tests\Feature\Tenant;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantIdentifierTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_identifier_is_generated_from_name(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Transportadora Silva']);

        $this->assertSame('transportadora-silva', $tenant->identifier);
    }

    public function test_identifier_generation_avoids_duplicates(): void
    {
        $first = Tenant::factory()->create(['name' => 'Transportadora Silva']);
        $second = Tenant::factory()->create(['name' => 'Transportadora Silva']);

        $this->assertSame('transportadora-silva', $first->identifier);
        $this->assertSame('transportadora-silva-2', $second->identifier);
    }

    public function test_identifier_can_be_explicitly_set_on_create(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Transportadora Silva',
            'identifier' => 'silva-log',
        ]);

        $this->assertSame('silva-log', $tenant->identifier);
    }

    public function test_public_endpoint_returns_tenant_branding_without_authentication(): void
    {
        $tenant = $this->createTenantWithRoles([
            'identifier' => 'minha-empresa',
            'app_name' => 'Rastreio Fácil',
            'logo_path' => 'uploads/logo.png',
            'app_logo_path' => 'uploads/app-logo.png',
            'app_primary_color' => '#5B5CE2',
            'app_secondary_color' => '#0EA5E9',
        ]);

        $this->getJson('/api/public/tenants/minha-empresa')
            ->assertOk()
            ->assertJsonPath('data.identifier', 'minha-empresa')
            ->assertJsonPath('data.name', $tenant->name)
            ->assertJsonPath('data.app_name', 'Rastreio Fácil')
            ->assertJsonPath('data.logo_url', asset('storage/uploads/logo.png'))
            ->assertJsonPath('data.app_logo_url', asset('storage/uploads/app-logo.png'))
            ->assertJsonPath('data.app_primary_color', '#5B5CE2')
            ->assertJsonPath('data.app_secondary_color', '#0EA5E9');
    }

    public function test_public_endpoint_is_case_insensitive(): void
    {
        $this->createTenantWithRoles(['identifier' => 'minha-empresa']);

        $this->getJson('/api/public/tenants/MINHA-EMPRESA')->assertOk();
    }

    public function test_public_endpoint_does_not_expose_sensitive_fields(): void
    {
        $this->createTenantWithRoles(['identifier' => 'minha-empresa']);

        $this->getJson('/api/public/tenants/minha-empresa')
            ->assertOk()
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.document')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.signature_url')
            ->assertJsonMissingPath('data.vehicle_alert_defaults');
    }

    public function test_public_endpoint_returns_not_found_for_unknown_identifier(): void
    {
        $this->getJson('/api/public/tenants/nao-existe')->assertNotFound();
    }

    public function test_authenticated_routes_still_require_authentication(): void
    {
        $this->createTenantWithRoles(['identifier' => 'minha-empresa']);

        $this->getJson('/api/tenant', ['X-Tenant-Id' => 'minha-empresa'])
            ->assertUnauthorized();
    }
}
