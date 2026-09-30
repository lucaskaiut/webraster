<?php

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BuildTenantEndpointTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.build.token', 'build-secret');
    }

    public function test_requires_valid_build_token(): void
    {
        $this->getJson('/api/build/tenants')->assertUnauthorized();

        $this->getJson('/api/build/tenants', ['Authorization' => 'Bearer errado'])
            ->assertUnauthorized();
    }

    public function test_returns_public_tenant_data_for_build(): void
    {
        $tenantA = $this->createTenantWithRoles([
            'name' => 'Empresa A',
            'identifier' => 'empresa-a',
            'app_name' => 'App A',
            'app_icon_path' => 'uploads/icon.png',
            'app_logo_path' => 'uploads/logo.png',
            'app_primary_color' => '#112233',
            'app_secondary_color' => '#445566',
        ]);

        $this->createTenantWithRoles(['name' => 'Empresa B', 'identifier' => 'empresa-b']);

        $this->getJson('/api/build/tenants', ['Authorization' => 'Bearer build-secret'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $tenantA->uuid)
            ->assertJsonPath('data.0.identifier', 'empresa-a')
            ->assertJsonPath('data.0.name', 'Empresa A')
            ->assertJsonPath('data.0.app_name', 'App A')
            ->assertJsonPath('data.0.app_icon_url', asset('storage/uploads/icon.png'))
            ->assertJsonPath('data.0.app_logo_url', asset('storage/uploads/logo.png'))
            ->assertJsonPath('data.0.app_primary_color', '#112233')
            ->assertJsonPath('data.0.app_secondary_color', '#445566')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.document')
            ->assertJsonMissingPath('data.0.phone');
    }

    public function test_endpoint_is_disabled_without_configured_token(): void
    {
        config()->set('services.build.token', null);

        $this->getJson('/api/build/tenants', ['Authorization' => 'Bearer build-secret'])
            ->assertUnauthorized();
    }
}
