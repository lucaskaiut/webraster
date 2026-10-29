<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\Concerns\SignsServiceOrders;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;
    use SignsServiceOrders;

    public function test_creates_service_order_with_sequential_code(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        $technician = User::factory()->for($tenant)->technician()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'priority' => 'high',
            'description' => 'Instalar rastreador',
            'scheduled_start_at' => now()->addDay()->setTime(9, 0)->toIso8601String(),
            'scheduled_end_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'technician_id' => $technician->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', '1')
            ->assertJsonPath('data.status', 'open');

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', '2');
    }

    public function test_list_includes_who_opened_each_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);

        ServiceOrder::factory()->forClient($client)->create([
            'created_by' => $admin->getKey(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/service-orders')
            ->assertOk()
            ->assertJsonPath('data.0.created_by.id', $admin->uuid)
            ->assertJsonPath('data.0.created_by.name', $admin->name);
    }

    public function test_rejects_vehicle_from_another_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $vehicleB = Vehicle::factory()->forClient($clientB)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/service-orders', [
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

        $this->signServiceOrder($order->uuid);

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

    public function test_status_updates_do_not_notify_but_cancellation_does(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        $technician = User::factory()->for($tenant)->technician()->create();

        $order = ServiceOrder::factory()->forClient($client)->create([
            'technician_id' => $technician->getKey(),
            'created_by' => $admin->getKey(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'in_progress',
        ])->assertOk();

        $this->assertSame(0, \DB::table('user_notifications')
            ->where('type', 'service_order.status_changed')
            ->count());

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertOk();

        $this->assertSame(0, \DB::table('user_notifications')
            ->where('type', 'service_order.status_changed')
            ->count());

        $this->assertDatabaseHas('user_notifications', [
            'type' => 'service_order.cancelled',
            'user_id' => $technician->getKey(),
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'type' => 'service_order.cancelled',
            'user_id' => $admin->getKey(),
        ]);
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
            'client_id' => $client->uuid,
        ])->assertForbidden();
    }

    public function test_without_view_all_lists_only_own_orders(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $viewer = $this->createMember($tenant);
        $other = $this->createMember($tenant);
        $viewer->roles()->firstOrFail()->revokePermissions(Permission::SERVICE_ORDER_VIEW_ALL);

        $start = now()->addDay()->setTime(9, 0);
        $own = ServiceOrder::factory()->forClient($client)->create([
            'number' => 1,
            'created_by' => $viewer->getKey(),
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);
        ServiceOrder::factory()->forClient($client)->create([
            'number' => 2,
            'created_by' => $other->getKey(),
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/service-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->uuid);

        $this->getJson('/api/service-orders/kanban')
            ->assertOk()
            ->assertJsonCount(1, 'data.open')
            ->assertJsonPath('data.open.0.id', $own->uuid);

        $this->getJson('/api/service-orders/calendar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->uuid);
    }

    public function test_without_view_all_cannot_access_orders_from_others(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $viewer = $this->createMember($tenant);
        $other = $this->createMember($tenant);
        $viewer->roles()->firstOrFail()->revokePermissions(Permission::SERVICE_ORDER_VIEW_ALL);

        $order = ServiceOrder::factory()->forClient($client)->create([
            'number' => 1,
            'created_by' => $other->getKey(),
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/service-orders/{$order->uuid}")->assertForbidden();
        $this->getJson("/api/service-orders/{$order->uuid}/history")->assertForbidden();
        $this->putJson("/api/service-orders/{$order->uuid}", ['description' => 'x'])->assertForbidden();
        $this->deleteJson("/api/service-orders/{$order->uuid}")->assertForbidden();
        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Teste',
        ])->assertForbidden();
    }

    public function test_cancel_permission_allows_only_cancellation(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createMember($tenant);
        $user->roles()->firstOrFail()->revokePermissions(Permission::SERVICE_ORDER_CHANGE_STATUS);

        $order = ServiceOrder::factory()->forClient($client)->create([
            'number' => 1,
            'created_by' => $user->getKey(),
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'in_progress',
        ])->assertForbidden();

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_change_status_without_cancel_permission_cannot_cancel(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createMember($tenant);
        $user->roles()->firstOrFail()->revokePermissions(Permission::SERVICE_ORDER_CANCEL);

        $order = ServiceOrder::factory()->forClient($client)->create(['number' => 1]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente desistiu',
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
        $technician = User::factory()->for($tenant)->technician()->create();
        Sanctum::actingAs($admin);

        $start = now()->addDay()->setTime(10, 0);
        $end = now()->addDay()->setTime(12, 0);

        ServiceOrder::factory()->forClient($client)->create([
            'number' => 20,
            'technician_id' => $technician->getKey(),
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'status' => ServiceOrderStatus::OPEN,
        ]);

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $start->copy()->addHour()->toIso8601String(),
            'scheduled_end_at' => $end->copy()->addHour()->toIso8601String(),
        ])->assertUnprocessable();

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $start->copy()->addHour()->toIso8601String(),
            'scheduled_end_at' => $end->copy()->addHour()->toIso8601String(),
            'ignore_schedule_conflict' => true,
        ])->assertCreated();
    }

    public function test_creates_order_without_client_and_with_attachments(): void
    {
        Storage::fake('local');
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);

        Sanctum::actingAs($admin);

        $response = $this->post('/api/service-orders', [
            'address' => 'Rua das Flores, 100 - Curitiba',
            'attachments' => [UploadedFile::fake()->create('pedido.pdf', 120, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.client_id', null)
            ->assertJsonPath('data.address', 'Rua das Flores, 100 - Curitiba')
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.name', 'pedido.pdf');

        $order = ServiceOrder::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        Storage::disk('local')->assertExists($order->attachments()->firstOrFail()->path);
    }

    public function test_rejects_attachment_with_unsupported_extension_on_create(): void
    {
        Storage::fake('local');
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);

        Sanctum::actingAs($admin);

        $this->post('/api/service-orders', [
            'address' => 'Rua das Flores, 100 - Curitiba',
            'attachments' => [UploadedFile::fake()->create('script.exe', 10)],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');

        $this->assertSame(0, ServiceOrder::query()->count());
    }

    public function test_billing_requires_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $order = ServiceOrder::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'client_id' => null,
            'created_by' => $admin->getKey(),
        ]);
        $order->items()->forceCreate(['tenant_id' => $tenant->getKey(), 'name' => 'Instalação', 'quantity' => 1, 'unit_amount_cents' => 10000]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/service-orders/{$order->uuid}/billing")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_id');
    }
}
