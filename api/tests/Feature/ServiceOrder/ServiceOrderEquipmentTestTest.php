<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
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

    public function test_failed_command_is_still_recorded_on_order(): void
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
            ->assertOk()
            ->assertJsonPath('data.action', 'block')
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('device_command_logs', [
            'service_order_id' => $order->getKey(),
            'command_type' => 'engineStop',
            'status' => DeviceCommandStatus::FAILED->value,
        ]);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'action' => 'equipment_tested',
            'field' => 'equipment_test',
            'new_value' => 'Bloqueio',
        ]);
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
