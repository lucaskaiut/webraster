<?php

namespace Tests\Feature\Equipment;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class EquipmentCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_store_creates_equipment(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/equipments', [
            'imei' => '359633100000001',
            'model' => 'FMB920',
            'iccid' => '8955051234567890123',
            'carrier' => 'Vivo',
        ])
            ->assertCreated()
            ->assertJsonPath('data.imei', '359633100000001')
            ->assertJsonPath('data.model', 'FMB920')
            ->assertJsonPath('data.is_assigned', false);

        $this->assertDatabaseHas('equipments', [
            'tenant_id' => $tenant->getKey(),
            'imei' => '359633100000001',
            'vehicle_id' => null,
        ]);
    }

    public function test_index_can_filter_available_only(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        Equipment::factory()->forTenant($tenant)->create(['imei' => '100000000000001']);
        Equipment::factory()->assignedTo($vehicle)->create(['imei' => '100000000000002']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/equipments?available=1')->assertOk();
        $imeis = collect($response->json('data'))->pluck('imei');

        $this->assertTrue($imeis->contains('100000000000001'));
        $this->assertFalse($imeis->contains('100000000000002'));
    }

    public function test_store_allows_reusing_imei_of_soft_deleted_equipment(): void
    {
        [, $tenant] = $this->createOperationalChild();

        $deleted = Equipment::factory()->forTenant($tenant)->create(['imei' => '359633100000001']);
        $deleted->delete();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/equipments', [
            'imei' => '359633100000001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.imei', '359633100000001');
    }

    public function test_cannot_delete_assigned_equipment(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->deleteJson("/api/equipments/{$equipment->uuid}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipment']);
    }

    public function test_update_and_destroy_unassigned_equipment(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $equipment = Equipment::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/equipments/{$equipment->uuid}", [
            'carrier' => 'Claro',
            'model' => 'GT06N',
        ])
            ->assertOk()
            ->assertJsonPath('data.carrier', 'Claro')
            ->assertJsonPath('data.model', 'GT06N');

        $this->deleteJson("/api/equipments/{$equipment->uuid}")->assertOk();

        $this->assertSoftDeleted('equipments', ['id' => $equipment->getKey()]);
    }
}
