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
        $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->uuid)
            ->assertJsonPath('data.domain', $tenant->domain);
    }

    public function test_update_operates_on_the_authenticated_tenant_only(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', [
            'name' => 'Novo Nome',
            'domain' => 'novo-dominio.com.br',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo Nome')
            ->assertJsonPath('data.domain', 'novo-dominio.com.br');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'name' => 'Novo Nome',
            'domain' => 'novo-dominio.com.br',
        ]);
    }

    public function test_update_ignores_tenant_id_sent_in_payload(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->putJson('/api/tenant', [
            'tenant_id' => $tenantB->getKey(),
            'id' => $tenantB->getKey(),
            'name' => 'Atualizado',
        ])->assertOk();

        $this->assertDatabaseHas('tenants', ['id' => $tenantA->getKey(), 'name' => 'Atualizado']);
        $this->assertDatabaseMissing('tenants', ['id' => $tenantB->getKey(), 'name' => 'Atualizado']);
    }

    public function test_update_validates_domain_and_document(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createTenantWithRoles(['domain' => 'existente.com.br']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', ['domain' => 'não é um domínio'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);

        $this->putJson('/api/tenant', ['domain' => 'existente.com.br'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);

        $this->putJson('/api/tenant', ['document' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);
    }

    public function test_member_without_permission_cannot_read_or_update_tenant(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMember($tenant));

        $this->getJson('/api/tenant')->assertForbidden()->assertJsonPath('success', false);
        $this->putJson('/api/tenant', ['name' => 'X'])->assertForbidden();
    }
}
