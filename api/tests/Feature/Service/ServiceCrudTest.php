<?php

namespace Tests\Feature\Service;

use App\Modules\Service\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_store_creates_service(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/services', [
            'name' => 'Instalação de rastreador',
            'amount_cents' => 15000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Instalação de rastreador')
            ->assertJsonPath('data.amount_cents', 15000)
            ->assertJsonPath('data.amount', '150.00');

        $this->assertDatabaseHas('services', [
            'tenant_id' => $tenant->getKey(),
            'name' => 'Instalação de rastreador',
            'amount_cents' => 15000,
        ]);
    }

    public function test_index_searches_by_name(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Service::factory()->forTenant($tenant)->create(['name' => 'Manutenção preventiva']);
        Service::factory()->forTenant($tenant)->create(['name' => 'Retirada de equipamento']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/services?search=Manutenção')->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Manutenção preventiva'));
        $this->assertFalse($names->contains('Retirada de equipamento'));
    }

    public function test_tenant_isolation(): void
    {
        [, $tenantA] = $this->createOperationalChild();
        [, $tenantB] = $this->createOperationalChild();

        Service::factory()->forTenant($tenantA)->create(['name' => 'Serviço A']);
        Service::factory()->forTenant($tenantB)->create(['name' => 'Serviço B']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $response = $this->getJson('/api/services')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Serviço A'));
        $this->assertFalse($names->contains('Serviço B'));
    }

    public function test_update_and_destroy_service(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $service = Service::factory()->forTenant($tenant)->create([
            'name' => 'Instalação',
            'amount_cents' => 10000,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/services/{$service->uuid}", [
            'name' => 'Instalação completa',
            'amount_cents' => 18500,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Instalação completa')
            ->assertJsonPath('data.amount_cents', 18500);

        $this->deleteJson("/api/services/{$service->uuid}")->assertOk();

        $this->assertSoftDeleted('services', ['id' => $service->getKey()]);
    }

    public function test_forbids_create_without_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createClient($tenant));

        $this->postJson('/api/services', [
            'name' => 'X',
            'amount_cents' => 1000,
        ])->assertForbidden();
    }
}
