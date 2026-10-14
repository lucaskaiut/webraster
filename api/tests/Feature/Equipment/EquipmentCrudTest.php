<?php

namespace Tests\Feature\Equipment;

use App\Modules\ACL\Enums\Permission;
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
            'line_number' => '11987654321',
            'carrier' => 'Vivo',
        ])
            ->assertCreated()
            ->assertJsonPath('data.imei', '359633100000001')
            ->assertJsonPath('data.model', 'FMB920')
            ->assertJsonPath('data.line_number', '11987654321')
            ->assertJsonPath('data.is_assigned', false);

        $this->assertDatabaseHas('equipments', [
            'tenant_id' => $tenant->getKey(),
            'imei' => '359633100000001',
            'vehicle_id' => null,
        ]);
    }

    public function test_index_hides_device_details_without_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '359633100000099',
            'model' => 'E3+4G',
            'line_number' => '11987654321',
            'carrier' => 'Vivo',
            'traccar_status' => 'online',
        ]);

        $user = $this->createMember($tenant);
        $user->roles()->firstOrFail()->revokePermissions(Permission::EQUIPMENT_DETAILS_READ);

        Sanctum::actingAs($user);

        $this->getJson('/api/equipments')
            ->assertOk()
            ->assertJsonPath('data.0.imei', null)
            ->assertJsonPath('data.0.model', null)
            ->assertJsonPath('data.0.line_number', null)
            ->assertJsonPath('data.0.carrier', null)
            ->assertJsonPath('data.0.traccar_status', 'online');
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

    public function test_index_can_filter_by_vehicle_and_status(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '100000000000011',
            'is_active' => true,
        ]);
        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '100000000000012',
            'is_active' => false,
        ]);
        Equipment::factory()->assignedTo($vehicle)->create([
            'imei' => '100000000000013',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $available = collect($this->getJson('/api/equipments?available=1')->assertOk()->json('data'))
            ->pluck('imei');
        $this->assertTrue($available->contains('100000000000011'));
        $this->assertFalse($available->contains('100000000000013'));

        $assigned = collect($this->getJson('/api/equipments?available=0')->assertOk()->json('data'))
            ->pluck('imei');
        $this->assertTrue($assigned->contains('100000000000013'));
        $this->assertFalse($assigned->contains('100000000000011'));
        $this->assertFalse($assigned->contains('100000000000012'));

        $active = collect($this->getJson('/api/equipments?is_active=1')->assertOk()->json('data'))
            ->pluck('imei');
        $this->assertTrue($active->contains('100000000000011'));
        $this->assertFalse($active->contains('100000000000012'));

        $inactive = collect($this->getJson('/api/equipments?is_active=0')->assertOk()->json('data'))
            ->pluck('imei');
        $this->assertTrue($inactive->contains('100000000000012'));
        $this->assertFalse($inactive->contains('100000000000011'));
    }

    public function test_index_exposes_traccar_connection_fields(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '359633100000042',
            'traccar_status' => 'online',
            'traccar_last_update' => '2026-09-21 16:10:15',
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/equipments')
            ->assertOk()
            ->assertJsonPath('data.0.traccar_status', 'online')
            ->assertJsonPath('data.0.traccar_last_update', '2026-09-21T16:10:15+00:00');
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

    public function test_store_rejects_imei_with_spaces_or_invalid_length(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/equipments', ['imei' => '359 633100000001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['imei']);

        $this->postJson('/api/equipments', ['imei' => '12345678901234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['imei']);

        $this->postJson('/api/equipments', ['imei' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['imei']);
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

    public function test_index_supports_all_records_and_oldest_sort(): void
    {
        [, $tenant] = $this->createOperationalChild();

        $first = Equipment::factory()->forTenant($tenant)->create(['imei' => '100000000000021']);
        $first->forceFill(['created_at' => now()->subDays(3)])->save();

        $second = Equipment::factory()->forTenant($tenant)->create(['imei' => '100000000000022']);
        $second->forceFill(['created_at' => now()->subDays(2)])->save();

        $third = Equipment::factory()->forTenant($tenant)->create(['imei' => '100000000000023']);
        $third->forceFill(['created_at' => now()->subDay()])->save();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/equipments?per_page=all')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3);

        $oldest = collect(
            $this->getJson('/api/equipments?sort=oldest')->assertOk()->json('data'),
        )->pluck('imei');

        $this->assertSame('100000000000021', $oldest->first());

        $newest = collect(
            $this->getJson('/api/equipments')->assertOk()->json('data'),
        )->pluck('imei');

        $this->assertSame('100000000000023', $newest->first());
    }

    public function test_index_supports_multiple_sort_columns(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '200000000000001',
            'carrier' => 'Vivo',
            'is_active' => false,
        ]);
        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '200000000000002',
            'carrier' => 'Vivo',
            'is_active' => true,
        ]);
        Equipment::factory()->forTenant($tenant)->create([
            'imei' => '200000000000003',
            'carrier' => 'Claro',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        // Ativos primeiro (true = 1); dentro de cada status, IMEI decrescente.
        $response = $this->getJson('/api/equipments?sort=is_active:desc,imei:desc')->assertOk();
        $imeis = collect($response->json('data'))->pluck('imei');

        $this->assertSame(
            ['200000000000003', '200000000000002', '200000000000001'],
            $imeis->all(),
        );

        // Critério inválido é ignorado e o padrão continua valendo.
        $this->getJson('/api/equipments?sort=coluna_invalida:asc')
            ->assertOk()
            ->assertJsonCount(3, 'data');
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
