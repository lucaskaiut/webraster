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

    public function test_update_persists_logo_and_favicon(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', [
            'logo_path' => 'uploads/logo.png',
            'favicon_path' => 'uploads/favicon.ico',
        ])
            ->assertOk()
            ->assertJsonPath('data.logo_path', 'uploads/logo.png')
            ->assertJsonPath('data.logo_url', asset('storage/uploads/logo.png'))
            ->assertJsonPath('data.favicon_path', 'uploads/favicon.ico')
            ->assertJsonPath('data.favicon_url', asset('storage/uploads/favicon.ico'));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'logo_path' => 'uploads/logo.png',
            'favicon_path' => 'uploads/favicon.ico',
        ]);

        $this->putJson('/api/tenant', ['logo_path' => null, 'favicon_path' => null])
            ->assertOk()
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.favicon_path', null)
            ->assertJsonPath('data.favicon_url', null);
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
