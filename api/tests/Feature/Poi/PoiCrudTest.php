<?php

namespace Tests\Feature\Poi;

use App\Modules\Client\Models\Client;
use App\Modules\Poi\Models\Poi;
use App\Modules\Poi\Models\PoiCategory;
use App\Modules\Poi\Services\PoiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PoiCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_crud_poi_with_category_and_filters(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        Sanctum::actingAs($this->createAdmin($tenant));

        app(PoiService::class)->ensureDefaultCategories($tenant);
        $category = PoiCategory::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('slug', 'oficina')
            ->firstOrFail();

        $create = $this->postJson('/api/pois', [
            'client_id' => $client->uuid,
            'poi_category_id' => $category->uuid,
            'name' => 'Oficina Central',
            'latitude' => -25.4284,
            'longitude' => -49.2733,
            'address' => 'Rua Teste, 100',
        ])->assertCreated();

        $poiId = $create->json('data.id');

        $this->getJson('/api/pois?category_id='.$category->uuid.'&search=Oficina')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Oficina Central');

        $this->putJson('/api/pois/'.$poiId, [
            'name' => 'Oficina Norte',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Oficina Norte')
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/poi-categories')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'oficina']);

        $this->deleteJson('/api/pois/'.$poiId)->assertOk();
        $this->assertSoftDeleted('pois', ['uuid' => $poiId]);
    }

    public function test_tenant_isolation_for_pois(): void
    {
        [, $tenantA] = $this->createOperationalChild();
        [, $tenantB] = $this->createOperationalChild();

        $clientA = Client::factory()->for($tenantA)->create();
        $clientB = Client::factory()->for($tenantB)->create();

        $categoryA = PoiCategory::factory()->forTenant($tenantA)->create(['slug' => 'base-a']);
        $categoryB = PoiCategory::factory()->forTenant($tenantB)->create(['slug' => 'base-b']);

        $poiB = Poi::factory()->forClient($clientB)->forCategory($categoryB)->create(['name' => 'POI B']);
        Poi::factory()->forClient($clientA)->forCategory($categoryA)->create(['name' => 'POI A']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->getJson('/api/pois')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'POI A');

        $this->getJson('/api/pois/'.$poiB->uuid)->assertNotFound();
    }
}
