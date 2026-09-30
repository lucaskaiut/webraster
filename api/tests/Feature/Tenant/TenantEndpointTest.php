<?php

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantEndpointTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_show_returns_only_the_current_tenant(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->uuid)
            ->assertJsonPath('data.name', $tenant->name);
    }

    public function test_update_operates_on_the_authenticated_tenant_only(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', [
            'name' => 'Novo Nome',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo Nome');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'name' => 'Novo Nome',
        ]);
    }

    public function test_update_ignores_tenant_id_sent_in_payload(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->putJson('/api/tenant', [
            'tenant_id' => $tenantB->getKey(),
            'id' => $tenantB->getKey(),
            'name' => 'Atualizado',
        ])->assertOk();

        $this->assertDatabaseHas('tenants', ['id' => $tenantA->getKey(), 'name' => 'Atualizado']);
        $this->assertDatabaseMissing('tenants', ['id' => $tenantB->getKey(), 'name' => 'Atualizado']);
    }

    public function test_update_persists_logo_favicon_and_signature(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', [
            'logo_path' => 'uploads/logo.png',
            'favicon_path' => 'uploads/favicon.ico',
            'signature_path' => 'uploads/assinatura.png',
        ])
            ->assertOk()
            ->assertJsonPath('data.logo_path', 'uploads/logo.png')
            ->assertJsonPath('data.logo_url', asset('storage/uploads/logo.png'))
            ->assertJsonPath('data.favicon_path', 'uploads/favicon.ico')
            ->assertJsonPath('data.favicon_url', asset('storage/uploads/favicon.ico'))
            ->assertJsonPath('data.signature_path', 'uploads/assinatura.png')
            ->assertJsonPath('data.signature_url', asset('storage/uploads/assinatura.png'));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'logo_path' => 'uploads/logo.png',
            'favicon_path' => 'uploads/favicon.ico',
            'signature_path' => 'uploads/assinatura.png',
        ]);

        $this->putJson('/api/tenant', [
            'logo_path' => null,
            'favicon_path' => null,
            'signature_path' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.favicon_path', null)
            ->assertJsonPath('data.favicon_url', null)
            ->assertJsonPath('data.signature_path', null)
            ->assertJsonPath('data.signature_url', null);
    }

    public function test_update_persists_app_settings(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', [
            'app_name' => 'Rastreio Fácil',
            'app_icon_path' => 'uploads/app-icon.png',
            'app_logo_path' => 'uploads/app-logo.png',
            'app_primary_color' => '#5B5CE2',
            'app_secondary_color' => '#0EA5E9',
        ])
            ->assertOk()
            ->assertJsonPath('data.app_name', 'Rastreio Fácil')
            ->assertJsonPath('data.app_icon_path', 'uploads/app-icon.png')
            ->assertJsonPath('data.app_icon_url', asset('storage/uploads/app-icon.png'))
            ->assertJsonPath('data.app_logo_path', 'uploads/app-logo.png')
            ->assertJsonPath('data.app_logo_url', asset('storage/uploads/app-logo.png'))
            ->assertJsonPath('data.app_primary_color', '#5B5CE2')
            ->assertJsonPath('data.app_secondary_color', '#0EA5E9');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'app_name' => 'Rastreio Fácil',
            'app_primary_color' => '#5B5CE2',
            'app_secondary_color' => '#0EA5E9',
        ]);

        $this->putJson('/api/tenant', [
            'app_name' => null,
            'app_icon_path' => null,
            'app_logo_path' => null,
            'app_primary_color' => null,
            'app_secondary_color' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.app_name', null)
            ->assertJsonPath('data.app_icon_url', null)
            ->assertJsonPath('data.app_logo_url', null)
            ->assertJsonPath('data.app_primary_color', null)
            ->assertJsonPath('data.app_secondary_color', null);
    }

    public function test_update_validates_app_colors(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', ['app_primary_color' => 'azul'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['app_primary_color']);

        $this->putJson('/api/tenant', ['app_secondary_color' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['app_secondary_color']);
    }

    public function test_update_can_change_identifier(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', ['identifier' => 'Novo-Identificador'])
            ->assertOk()
            ->assertJsonPath('data.identifier', 'novo-identificador');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'identifier' => 'novo-identificador',
        ]);
    }

    public function test_update_rejects_identifier_already_in_use(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createTenantWithRoles(['identifier' => 'em-uso']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', ['identifier' => 'em-uso'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_update_validates_document(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', ['document' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);
    }

    public function test_member_can_read_but_not_update_tenant(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMember($tenant));

        $this->getJson('/api/tenant')->assertOk();
        $this->putJson('/api/tenant', ['name' => 'X'])->assertForbidden();
    }
}
