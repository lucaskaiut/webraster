<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Enums\ServiceOrderType;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_creates_service_order_with_sequential_code(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);

        Sanctum::actingAs($admin);

        $this->postJson('/api/service-orders', [
            'type' => ServiceOrderType::INSTALLATION->value,
            'client_id' => $client->uuid,
            'priority' => 'high',
            'description' => 'Instalar rastreador',
            'scheduled_start_at' => now()->addDay()->setTime(9, 0)->toIso8601String(),
            'scheduled_end_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'technician_id' => $admin->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'OS-000001')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.type', 'installation');

        $this->postJson('/api/service-orders', [
            'type' => ServiceOrderType::MAINTENANCE->value,
            'client_id' => $client->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'OS-000002');
    }

    public function test_rejects_vehicle_from_another_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $vehicleB = Vehicle::factory()->forClient($clientB)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/service-orders', [
            'type' => 'installation',
            'client_id' => $clientA->uuid,
            'vehicle_id' => $vehicleB->uuid,
        ])->assertUnprocessable();
    }

    public function test_status_transitions_and_blocks_invalid(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $order = ServiceOrder::factory()->forClient($client)->create([
            'number' => 10,
            'created_by' => $admin->getKey(),
        ]);

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'completed',
        ])->assertUnprocessable();

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'in_progress',
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'completed',
            'execution_notes' => 'Serviço ok',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'open',
        ])->assertUnprocessable();
    }

    public function test_cancel_requires_reason(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $order = ServiceOrder::factory()->forClient($client)->create(['number' => 11]);

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'cancelled',
        ])->assertUnprocessable();

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente desistiu',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Cliente desistiu');
    }

    public function test_tenant_isolation(): void
    {
        [, $tenantA] = $this->createOperationalChild();
        [, $tenantB] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenantA)->create();
        $order = ServiceOrder::factory()->forClient($clientA)->create(['number' => 1]);

        Sanctum::actingAs($this->createAdmin($tenantB));
        $this->getJson('/api/service-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/service-orders/{$order->uuid}")->assertNotFound();
        $this->getJson('/api/service-orders/kanban')->assertOk();
    }

    public function test_forbids_without_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createClient($tenant));

        $this->postJson('/api/service-orders', [
            'type' => 'installation',
            'client_id' => $client->uuid,
        ])->assertForbidden();
    }

    public function test_kanban_groups_by_status(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        ServiceOrder::factory()->forClient($client)->create(['number' => 1, 'status' => ServiceOrderStatus::OPEN]);
        ServiceOrder::factory()->forClient($client)->create(['number' => 2, 'status' => ServiceOrderStatus::IN_PROGRESS]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/service-orders/kanban')
            ->assertOk()
            ->assertJsonCount(1, 'data.open')
            ->assertJsonCount(1, 'data.in_progress');
    }

    public function test_schedule_conflict_detection(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $start = now()->addDay()->setTime(10, 0);
        $end = now()->addDay()->setTime(12, 0);

        ServiceOrder::factory()->forClient($client)->create([
            'number' => 20,
            'technician_id' => $admin->getKey(),
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'status' => ServiceOrderStatus::OPEN,
        ]);

        $this->postJson('/api/service-orders', [
            'type' => 'maintenance',
            'client_id' => $client->uuid,
            'technician_id' => $admin->uuid,
            'scheduled_start_at' => $start->copy()->addHour()->toIso8601String(),
            'scheduled_end_at' => $end->copy()->addHour()->toIso8601String(),
        ])->assertUnprocessable();

        $this->postJson('/api/service-orders', [
            'type' => 'maintenance',
            'client_id' => $client->uuid,
            'technician_id' => $admin->uuid,
            'scheduled_start_at' => $start->copy()->addHour()->toIso8601String(),
            'scheduled_end_at' => $end->copy()->addHour()->toIso8601String(),
            'ignore_schedule_conflict' => true,
        ])->assertCreated();
    }
}
