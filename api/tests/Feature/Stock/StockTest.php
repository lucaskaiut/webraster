<?php

namespace Tests\Feature\Stock;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Stock\Enums\EquipmentStockMovementType;
use App\Modules\Stock\Models\EquipmentStockMovement;
use App\Modules\Stock\Models\Stock;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\Concerns\SignsServiceOrders;
use Tests\TestCase;

class StockTest extends TestCase
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

    public function test_manages_stocks_and_blocks_delete_when_linked(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $stockId = $this->postJson('/api/stocks', [
            'name' => 'Estoque Central',
            'description' => 'Estoque principal da operação.',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Estoque Central')
            ->json('data.id');

        $this->getJson('/api/stocks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.technicians_count', 0);

        $this->putJson("/api/stocks/{$stockId}", [
            'name' => 'Estoque Central atualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Estoque Central atualizado');

        $stock = Stock::query()->where('uuid', $stockId)->firstOrFail();
        Equipment::factory()->forStock($stock)->create();

        $this->deleteJson("/api/stocks/{$stockId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stock');

        $this->assertDatabaseHas('stocks', ['id' => $stock->getKey()]);
    }

    public function test_equipment_entry_and_transfer_are_audited(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $source = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque A']);
        $target = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque B']);

        $equipmentId = $this->postJson('/api/equipments', [
            'imei' => '359633100000123',
            'model' => 'FMB920',
            'stock_id' => $source->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.stock_id', $source->uuid)
            ->json('data.id');

        $this->assertDatabaseHas('equipment_stock_movements', [
            'equipment_id' => Equipment::query()->where('uuid', $equipmentId)->value('id'),
            'from_stock_id' => null,
            'to_stock_id' => $source->getKey(),
            'type' => EquipmentStockMovementType::ENTRY->value,
            'moved_by' => $admin->getKey(),
        ]);

        $this->putJson("/api/equipments/{$equipmentId}", [
            'stock_id' => $target->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('data.stock_id', $target->uuid);

        $movement = EquipmentStockMovement::query()
            ->where('type', EquipmentStockMovementType::TRANSFER->value)
            ->firstOrFail();

        $this->assertSame($source->getKey(), (int) $movement->from_stock_id);
        $this->assertSame($target->getKey(), (int) $movement->to_stock_id);
        $this->assertSame($admin->getKey(), (int) $movement->moved_by);

        $this->getJson("/api/equipments/{$equipmentId}/stock-movements")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'transfer')
            ->assertJsonPath('data.0.moved_by.name', $admin->name)
            ->assertJsonPath('data.1.type', 'entry');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->getKey(),
            'action' => 'equipment_stock_moved',
            'entity_type' => 'equipment',
        ]);
    }

    public function test_installation_and_removal_move_equipment_out_of_and_back_to_stock(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $stock = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque de instalação']);
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->forStock($stock)->create();

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/install", [
            'equipment_id' => $equipment->uuid,
        ])->assertOk();

        $equipment->refresh();
        $this->assertNull($equipment->stock_id);
        $this->assertSame($vehicle->getKey(), (int) $equipment->vehicle_id);

        $installation = EquipmentStockMovement::query()
            ->where('type', EquipmentStockMovementType::INSTALLATION->value)
            ->firstOrFail();

        $this->assertSame($stock->getKey(), (int) $installation->from_stock_id);
        $this->assertNull($installation->to_stock_id);
        $this->assertSame($admin->getKey(), (int) $installation->moved_by);
        $this->assertStringContainsString($vehicle->plate, $installation->description);

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/remove")->assertOk();

        $equipment->refresh();
        $this->assertNull($equipment->vehicle_id);
        $this->assertSame($stock->getKey(), (int) $equipment->stock_id);

        $return = EquipmentStockMovement::query()
            ->where('type', EquipmentStockMovementType::RETURN->value)
            ->firstOrFail();

        $this->assertNull($return->from_stock_id);
        $this->assertSame($stock->getKey(), (int) $return->to_stock_id);
        $this->assertSame($admin->getKey(), (int) $return->moved_by);
    }

    public function test_swap_audits_return_and_new_installation(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $stockA = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque A']);
        $stockB = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque B']);
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipmentA = Equipment::factory()->forStock($stockA)->create(['imei' => '111111111111101']);
        $equipmentB = Equipment::factory()->forStock($stockB)->create(['imei' => '111111111111102']);

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/install", [
            'equipment_id' => $equipmentA->uuid,
        ])->assertOk();

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/swap", [
            'equipment_id' => $equipmentB->uuid,
        ])->assertOk();

        $this->assertSame($stockA->getKey(), (int) $equipmentA->fresh()->stock_id);
        $this->assertNull($equipmentB->fresh()->stock_id);

        $this->assertDatabaseHas('equipment_stock_movements', [
            'equipment_id' => $equipmentA->getKey(),
            'type' => EquipmentStockMovementType::RETURN->value,
            'to_stock_id' => $stockA->getKey(),
        ]);
        $this->assertDatabaseHas('equipment_stock_movements', [
            'equipment_id' => $equipmentB->getKey(),
            'type' => EquipmentStockMovementType::INSTALLATION->value,
            'from_stock_id' => $stockB->getKey(),
        ]);
    }

    public function test_completing_service_order_installs_equipment_and_moves_stock(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $stock = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque da OS']);
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->forStock($stock)->create();

        $order = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'vehicle_id' => $vehicle->uuid,
            'equipment_id' => $equipment->uuid,
            'status' => 'in_progress',
        ])
            ->assertCreated()
            ->json('data');

        $orderId = $order['id'];

        $this->signServiceOrder($orderId);

        $this->patchJson("/api/service-orders/{$orderId}/status", [
            'status' => 'completed',
        ])->assertOk();

        $equipment->refresh();
        $this->assertSame($vehicle->getKey(), (int) $equipment->vehicle_id);
        $this->assertNull($equipment->stock_id);

        $movement = EquipmentStockMovement::query()
            ->where('equipment_id', $equipment->getKey())
            ->where('type', EquipmentStockMovementType::INSTALLATION->value)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($stock->getKey(), (int) $movement->from_stock_id);
        $this->assertSame($admin->getKey(), (int) $movement->moved_by);
        $this->assertStringContainsString("Concluída pela OS {$order['code']}.", $movement->description);
    }

    public function test_completing_service_order_swaps_vehicle_equipment_and_returns_previous_to_stock(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $stockA = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque A']);
        $stockB = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque B']);
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $installed = Equipment::factory()->forStock($stockA)->create();
        $newEquipment = Equipment::factory()->forStock($stockB)->create();

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/install", [
            'equipment_id' => $installed->uuid,
        ])->assertOk();

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'vehicle_id' => $vehicle->uuid,
            'equipment_id' => $newEquipment->uuid,
            'status' => 'in_progress',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->signServiceOrder($orderId);

        $this->patchJson("/api/service-orders/{$orderId}/status", [
            'status' => 'completed',
        ])->assertOk();

        $this->assertSame($stockA->getKey(), (int) $installed->fresh()->stock_id);
        $this->assertNull($installed->fresh()->vehicle_id);
        $this->assertSame($vehicle->getKey(), (int) $newEquipment->fresh()->vehicle_id);
        $this->assertNull($newEquipment->fresh()->stock_id);

        $this->assertDatabaseHas('equipment_stock_movements', [
            'equipment_id' => $installed->getKey(),
            'type' => EquipmentStockMovementType::RETURN->value,
            'to_stock_id' => $stockA->getKey(),
        ]);
        $this->assertDatabaseHas('equipment_stock_movements', [
            'equipment_id' => $newEquipment->getKey(),
            'type' => EquipmentStockMovementType::INSTALLATION->value,
            'from_stock_id' => $stockB->getKey(),
        ]);
    }

    public function test_assigned_equipment_stock_cannot_be_changed(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $source = Stock::factory()->forTenant($tenant)->create();
        $target = Stock::factory()->forTenant($tenant)->create();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->forStock($source)->create();

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/install", [
            'equipment_id' => $equipment->uuid,
        ])->assertOk();

        $this->putJson("/api/equipments/{$equipment->uuid}", [
            'stock_id' => $target->uuid,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stock_id');
    }

    public function test_technician_stock_limits_equipment_options_and_service_order_selection(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $stockA = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque A']);
        $stockB = Stock::factory()->forTenant($tenant)->create(['name' => 'Estoque B']);
        $equipmentA = Equipment::factory()->forStock($stockA)->create(['imei' => '100000000000101']);
        $equipmentB = Equipment::factory()->forStock($stockB)->create(['imei' => '100000000000102']);

        $technicianA = User::factory()->for($tenant)->technician()->create([
            'stock_id' => $stockA->getKey(),
        ]);
        $technicianNoStock = User::factory()->for($tenant)->technician()->create([
            'stock_id' => null,
        ]);
        $client = Client::factory()->for($tenant)->create();

        $response = $this->getJson(
            '/api/equipments?available=1&technician_id='.$technicianA->uuid,
        )->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($equipmentA->uuid));
        $this->assertFalse($ids->contains($equipmentB->uuid));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technicianA->uuid,
            'equipment_id' => $equipmentB->uuid,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment_id');

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technicianA->uuid,
            'equipment_id' => $equipmentA->uuid,
        ])->assertCreated();

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technicianNoStock->uuid,
            'equipment_id' => $equipmentB->uuid,
        ])->assertCreated();
    }

    public function test_technician_can_have_stock_and_staff_stock_is_cleared(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $stock = Stock::factory()->forTenant($tenant)->create();
        $technician = User::factory()->for($tenant)->technician()->create(['stock_id' => null]);

        $this->putJson("/api/users/{$technician->uuid}", [
            'stock_id' => $stock->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('data.stock_id', $stock->uuid)
            ->assertJsonPath('data.stock.name', $stock->name);

        $this->putJson("/api/users/{$technician->uuid}", [
            'stock_id' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.stock_id', null);

        $staff = User::factory()->for($tenant)->create(['stock_id' => $stock->getKey()]);

        $this->putJson("/api/users/{$staff->uuid}", [
            'name' => $staff->name,
        ])->assertOk();

        $this->assertNull($staff->fresh()->stock_id);
    }
}
