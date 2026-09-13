<?php

namespace Tests\Feature\Contract;

use App\Modules\Contract\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ContractCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_store_creates_contract(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/contracts', [
            'name' => 'Contrato padrão de rastreamento',
            'body' => '<p>Olá {{NOME_CLIENTE}}, documento {{DOCUMENTO_CLIENTE}}.</p>',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Contrato padrão de rastreamento')
            ->assertJsonPath('data.body', '<p>Olá {{NOME_CLIENTE}}, documento {{DOCUMENTO_CLIENTE}}.</p>');

        $this->assertDatabaseHas('contracts', [
            'tenant_id' => $tenant->getKey(),
            'name' => 'Contrato padrão de rastreamento',
        ]);
    }

    public function test_index_searches_by_name(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Contract::factory()->forTenant($tenant)->create(['name' => 'Contrato mensal']);
        Contract::factory()->forTenant($tenant)->create(['name' => 'Aditivo anual']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/contracts?search=mensal')->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Contrato mensal'));
        $this->assertFalse($names->contains('Aditivo anual'));
    }

    public function test_tenant_isolation(): void
    {
        [, $tenantA] = $this->createOperationalChild();
        [, $tenantB] = $this->createOperationalChild();

        Contract::factory()->forTenant($tenantA)->create(['name' => 'Contrato A']);
        Contract::factory()->forTenant($tenantB)->create(['name' => 'Contrato B']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $response = $this->getJson('/api/contracts')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Contrato A'));
        $this->assertFalse($names->contains('Contrato B'));
    }

    public function test_update_and_destroy_contract(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $contract = Contract::factory()->forTenant($tenant)->create([
            'name' => 'Contrato base',
            'body' => '<p>Texto antigo</p>',
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/contracts/{$contract->uuid}", [
            'name' => 'Contrato revisado',
            'body' => '<p>Texto novo com {{EMAIL_CLIENTE}}</p>',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Contrato revisado')
            ->assertJsonPath('data.body', '<p>Texto novo com {{EMAIL_CLIENTE}}</p>');

        $this->deleteJson("/api/contracts/{$contract->uuid}")->assertOk();

        $this->assertSoftDeleted('contracts', ['id' => $contract->getKey()]);
    }

    public function test_forbids_create_without_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createClient($tenant));

        $this->postJson('/api/contracts', [
            'name' => 'X',
            'body' => '<p>Y</p>',
        ])->assertForbidden();
    }
}
