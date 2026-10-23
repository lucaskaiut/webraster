<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Stock\Models\Stock;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\Concerns\SignsServiceOrders;
use Tests\TestCase;

class TechnicianEquipmentSelectionTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;
    use SignsServiceOrders;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'traccar.enabled' => false,
            'scheduling.geocoding.enabled' => false,
            'scheduling.routing.enabled' => false,
        ]);
    }

    public function test_technician_selects_equipment_from_own_stock_after_starting_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $stock = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque do técnico']);
        $otherStock = Stock::factory()->forTenant($tenant)->create(['name' => 'Outro estoque']);
        $technician = $this->createTechnician($tenant, ['stock_id' => $stock->getKey()]);

        $ownEquipment = Equipment::factory()->forStock($stock)->create([
            'imei' => '111111111111111',
            'model' => 'FMB920',
        ]);
        Equipment::factory()->forStock($stock)->create([
            'imei' => '222222222222222',
            'model' => 'GT06N',
        ]);
        $foreignEquipment = Equipment::factory()->forStock($otherStock)->create([
            'imei' => '333333333333333',
        ]);

        Sanctum::actingAs($admin);

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'vehicle_id' => $vehicle->uuid,
            'technician_id' => $technician->uuid,
        ])->assertCreated()->json('data.id');

        $order = ServiceOrder::query()->where('uuid', $orderId)->firstOrFail();

        Sanctum::actingAs($technician);

        $this->getJson("/api/technician/service-orders/{$orderId}/available-equipments")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->patchJson("/api/technician/service-orders/{$orderId}/status", [
            'status' => 'in_progress',
        ])->assertOk();

        $available = $this->getJson("/api/technician/service-orders/{$orderId}/available-equipments")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $available);
        $this->assertContains($ownEquipment->uuid, array_column($available, 'id'));
        $this->assertNotContains($foreignEquipment->uuid, array_column($available, 'id'));

        $this->putJson("/api/technician/service-orders/{$orderId}/equipment", [
            'equipment_id' => $foreignEquipment->uuid,
        ])->assertUnprocessable()->assertJsonValidationErrors('equipment_id');

        $this->putJson("/api/technician/service-orders/{$orderId}/equipment", [
            'equipment_id' => $ownEquipment->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('data.equipment.id', $ownEquipment->uuid)
            ->assertJsonPath('data.equipment.imei', '111111111111111');

        $this->assertDatabaseHas('service_orders', [
            'id' => $order->getKey(),
            'equipment_id' => $ownEquipment->getKey(),
        ]);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'field' => 'equipment_id',
            'new_value' => (string) $ownEquipment->getKey(),
        ]);

        $this->signServiceOrder($orderId);

        $this->patchJson("/api/technician/service-orders/{$orderId}/status", [
            'status' => 'completed',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('equipments', [
            'id' => $ownEquipment->getKey(),
            'vehicle_id' => $vehicle->getKey(),
        ]);
        $this->assertDatabaseHas('equipment_assignment_events', [
            'equipment_id' => $ownEquipment->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'event' => 'installation',
        ]);
    }

    public function test_technician_without_stock_can_select_any_available_equipment(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $technician = $this->createTechnician($tenant);

        $stockA = Stock::factory()->forTenant($tenant)->create();
        $stockB = Stock::factory()->forTenant($tenant)->create();
        $equipmentA = Equipment::factory()->forStock($stockA)->create();
        $equipmentB = Equipment::factory()->forStock($stockB)->create();

        Sanctum::actingAs($admin);

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'vehicle_id' => $vehicle->uuid,
            'technician_id' => $technician->uuid,
            'status' => 'in_progress',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($technician);

        $available = $this->getJson("/api/technician/service-orders/{$orderId}/available-equipments")
            ->assertOk()
            ->json('data');

        $ids = array_column($available, 'id');
        $this->assertContains($equipmentA->uuid, $ids);
        $this->assertContains($equipmentB->uuid, $ids);

        $this->putJson("/api/technician/service-orders/{$orderId}/equipment", [
            'equipment_id' => $equipmentB->uuid,
        ])->assertOk()->assertJsonPath('data.equipment.id', $equipmentB->uuid);
    }

    public function test_other_technician_cannot_change_order_equipment(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $stock = Stock::factory()->forTenant($tenant)->create();
        $technician = $this->createTechnician($tenant, ['stock_id' => $stock->getKey()]);
        $otherTechnician = $this->createTechnician($tenant, ['stock_id' => $stock->getKey()]);
        $equipment = Equipment::factory()->forStock($stock)->create();

        Sanctum::actingAs($admin);

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'vehicle_id' => $vehicle->uuid,
            'technician_id' => $technician->uuid,
            'status' => 'in_progress',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($otherTechnician);

        $this->putJson("/api/technician/service-orders/{$orderId}/equipment", [
            'equipment_id' => $equipment->uuid,
        ])->assertNotFound();

        $this->getJson("/api/technician/service-orders/{$orderId}/available-equipments")
            ->assertNotFound();
    }

    private function createTechnician(Tenant $tenant, array $attributes = []): User
    {
        $technician = User::factory()->for($tenant)->create([
            'type' => UserType::TECHNICIAN->value,
            ...$attributes,
        ]);

        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        return $technician;
    }
}
