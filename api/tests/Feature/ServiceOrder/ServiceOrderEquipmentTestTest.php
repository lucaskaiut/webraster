<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceOrderEquipmentTestTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'traccar.enabled' => false,
            'scheduling.geocoding.enabled' => false,
            'scheduling.routing.enabled' => false,
        ]);
    }

    public function test_technician_runs_block_and_unblock_tests_and_records_them_on_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();

        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create([
            'traccar_device_id' => 42,
        ]);
        $order = $this->makeOrder($client, $technician, $equipment);

        $this->fakeTraccarCommands();

        Sanctum::actingAs($technician);

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'block',
        ])
            ->assertOk()
            ->assertJsonPath('data.action', 'block')
            ->assertJsonPath('data.status', 'success');

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'unblock',
        ])
            ->assertOk()
            ->assertJsonPath('data.action', 'unblock')
            ->assertJsonPath('data.status', 'success');

        $this->assertDatabaseHas('device_command_logs', [
            'service_order_id' => $order->getKey(),
            'equipment_id' => $equipment->getKey(),
            'user_id' => $technician->getKey(),
            'command_type' => 'engineStop',
            'status' => DeviceCommandStatus::SUCCESS->value,
        ]);

        $this->assertDatabaseHas('device_command_logs', [
            'service_order_id' => $order->getKey(),
            'equipment_id' => $equipment->getKey(),
            'user_id' => $technician->getKey(),
            'command_type' => 'engineResume',
            'status' => DeviceCommandStatus::SUCCESS->value,
        ]);

        $this->assertDatabaseHas('service_order_equipment_tests', [
            'service_order_id' => $order->getKey(),
            'equipment_id' => $equipment->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'block',
            'command_type' => 'engineStop',
            'status' => DeviceCommandStatus::SUCCESS->value,
        ]);

        $this->assertDatabaseHas('service_order_equipment_tests', [
            'service_order_id' => $order->getKey(),
            'equipment_id' => $equipment->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'unblock',
            'command_type' => 'engineResume',
            'status' => DeviceCommandStatus::SUCCESS->value,
        ]);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
            'new_value' => 'Bloqueio',
        ]);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
            'new_value' => 'Desbloqueio',
        ]);

        $this->getJson("/api/technician/service-orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonCount(2, 'data.equipment_tests')
            ->assertJsonPath('data.equipment_tests.0.action', 'unblock')
            ->assertJsonPath('data.equipment_tests.1.action', 'block');
    }

    public function test_failed_command_is_not_recorded_on_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();

        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create([
            'traccar_device_id' => 42,
        ]);
        $order = $this->makeOrder($client, $technician, $equipment);

        Http::fake([
            'http://traccar.test/api/commands/types*' => Http::response([
                ['type' => 'engineStop'],
            ]),
            'http://traccar.test/api/commands/send' => Http::response([
                'message' => 'Device offline',
            ], 500),
        ]);

        Sanctum::actingAs($technician);

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'block',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');

        // O log do comando continua auditado...
        $this->assertDatabaseHas('device_command_logs', [
            'service_order_id' => $order->getKey(),
            'command_type' => 'engineStop',
            'status' => DeviceCommandStatus::FAILED->value,
        ]);

        // ...mas nenhum teste é registrado na OS.
        $this->assertDatabaseMissing('service_order_equipment_tests', [
            'service_order_id' => $order->getKey(),
            'action' => 'block',
        ]);

        $this->assertDatabaseMissing('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
        ]);

        $this->getJson("/api/technician/service-orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonCount(0, 'data.equipment_tests');
    }

    public function test_requires_selected_equipment_and_own_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();

        $technician = $this->createTechnician($tenant);
        $otherTechnician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();
        $order = $this->makeOrder($client, $technician, null);

        Sanctum::actingAs($technician);

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'block',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment_id');

        $equipment = Equipment::factory()->forTenant($tenant)->create([
            'traccar_device_id' => 42,
        ]);
        $order->forceFill(['equipment_id' => $equipment->getKey()])->save();

        Sanctum::actingAs($otherTechnician);

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'block',
        ])->assertNotFound();
    }

    public function test_technician_reads_ignition_from_latest_equipment_position(): void
    {
        [, $tenant] = $this->createOperationalChild();

        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create();
        $order = $this->makeOrder($client, $technician, $equipment);

        GpsPosition::factory()->forStockEquipment($equipment)->create([
            'ignition' => false,
            'recorded_at' => now()->subMinutes(10),
        ]);
        GpsPosition::factory()->forStockEquipment($equipment)->create([
            'ignition' => true,
            'recorded_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($technician);

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'ignition',
        ])
            ->assertOk()
            ->assertJsonPath('data.action', 'ignition')
            ->assertJsonPath('data.action_label', 'Ignição')
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.ignition', true);

        $this->assertDatabaseHas('service_order_equipment_tests', [
            'service_order_id' => $order->getKey(),
            'equipment_id' => $equipment->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'ignition',
            'status' => DeviceCommandStatus::SUCCESS->value,
        ]);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
            'new_value' => 'Ignição ligada',
        ]);

        // Uma posição mais recente com a ignição desligada passa a valer no
        // próximo teste, sem sobrescrever o registro anterior.
        GpsPosition::factory()->forStockEquipment($equipment)->create([
            'ignition' => false,
            'recorded_at' => now(),
        ]);

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'ignition',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.ignition', false);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
            'new_value' => 'Ignição desligada',
        ]);

        $this->getJson("/api/technician/service-orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonCount(2, 'data.equipment_tests')
            ->assertJsonPath('data.equipment_tests.0.action', 'ignition')
            ->assertJsonPath('data.equipment_tests.0.ignition', false)
            ->assertJsonPath('data.equipment_tests.1.ignition', true);
    }

    public function test_ignition_test_is_not_recorded_without_positions(): void
    {
        [, $tenant] = $this->createOperationalChild();

        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create();
        $order = $this->makeOrder($client, $technician, $equipment);

        Sanctum::actingAs($technician);

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'ignition',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');

        $this->assertDatabaseMissing('service_order_equipment_tests', [
            'service_order_id' => $order->getKey(),
            'action' => 'ignition',
        ]);

        $this->assertDatabaseMissing('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
        ]);
    }

    public function test_technician_reads_equipment_location_and_confirms_it(): void
    {
        [, $tenant] = $this->createOperationalChild();

        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create([
            'imei' => '444444444444444',
        ]);
        $order = $this->makeOrder($client, $technician, $equipment);

        GpsPosition::factory()->forStockEquipment($equipment)->create([
            'latitude' => -25.4284,
            'longitude' => -49.2733,
            'recorded_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($technician);

        $this->getJson("/api/technician/service-orders/{$order->uuid}/equipment-location")
            ->assertOk()
            ->assertJsonPath('data.latitude', -25.4284)
            ->assertJsonPath('data.longitude', -49.2733);

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'location',
        ])
            ->assertOk()
            ->assertJsonPath('data.action', 'location')
            ->assertJsonPath('data.action_label', 'Localização')
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.latitude', -25.4284)
            ->assertJsonPath('data.longitude', -49.2733);

        $this->assertDatabaseHas('service_order_equipment_tests', [
            'service_order_id' => $order->getKey(),
            'equipment_id' => $equipment->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'location',
            'status' => DeviceCommandStatus::SUCCESS->value,
        ]);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
            'new_value' => 'Localização confirmada',
        ]);

        $this->getJson("/api/technician/service-orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data.equipment_tests')
            ->assertJsonPath('data.equipment_tests.0.action', 'location')
            ->assertJsonPath('data.equipment_tests.0.latitude', -25.4284);
    }

    public function test_location_test_is_not_recorded_without_equipment_position(): void
    {
        [, $tenant] = $this->createOperationalChild();

        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create();
        $order = $this->makeOrder($client, $technician, $equipment);

        Sanctum::actingAs($technician);

        $this->getJson("/api/technician/service-orders/{$order->uuid}/equipment-location")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('location');

        $this->postJson("/api/technician/service-orders/{$order->uuid}/equipment-tests", [
            'action' => 'location',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');

        $this->assertDatabaseMissing('service_order_equipment_tests', [
            'service_order_id' => $order->getKey(),
            'action' => 'location',
        ]);

        $this->assertDatabaseMissing('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
        ]);
    }

    private function makeOrder(
        Client $client,
        User $technician,
        ?Equipment $equipment,
    ): ServiceOrder {
        return ServiceOrder::factory()->forClient($client)->inProgress()->create([
            'technician_id' => $technician->getKey(),
            'equipment_id' => $equipment?->getKey(),
        ]);
    }

    private function createTechnician($tenant): User
    {
        $technician = User::factory()->for($tenant)->technician()->create();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        return $technician;
    }

    private function enableTraccarGateway(): void
    {
        config([
            'traccar.enabled' => true,
            'traccar.base_url' => 'http://traccar.test',
            'traccar.token' => 'test-token',
            'traccar.timeout' => 5,
        ]);

        $this->app->instance(TraccarGateway::class, $this->app->make(HttpTraccarGateway::class));
    }

    private function fakeTraccarCommands(): void
    {
        Http::fake([
            'http://traccar.test/api/commands/types*' => Http::response([
                ['type' => 'engineStop'],
                ['type' => 'engineResume'],
            ]),
            'http://traccar.test/api/commands/send' => Http::response([
                'id' => 99,
                'deviceId' => 42,
                'type' => 'engineStop',
            ]),
        ]);
    }
}
