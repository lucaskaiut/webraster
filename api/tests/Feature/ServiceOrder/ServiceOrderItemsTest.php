<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\Concerns\SignsServiceOrders;
use Tests\TestCase;

class ServiceOrderItemsTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;
    use SignsServiceOrders;

    public function test_creates_order_with_items_snapshotting_catalog_service(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create([
            'name' => 'Instalação de rastreador',
            'amount_cents' => 15000,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 2,
                'unit_amount_cents' => 12000,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.name', 'Instalação de rastreador')
            ->assertJsonPath('data.items.0.service_id', $service->uuid)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_amount_cents', 12000)
            ->assertJsonPath('data.items.0.total_cents', 24000)
            ->assertJsonPath('data.items_total_cents', 24000);

        $this->assertDatabaseHas('service_order_items', [
            'tenant_id' => $tenant->getKey(),
            'service_id' => $service->getKey(),
            'quantity' => 2,
            'unit_amount_cents' => 12000,
        ]);
    }

    public function test_rejects_item_from_another_tenant(): void
    {
        [, $tenant] = $this->createOperationalChild();
        [, $otherTenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $foreignService = Service::factory()->forTenant($otherTenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'items' => [[
                'service_id' => $foreignService->uuid,
                'unit_amount_cents' => 1000,
            ]],
        ])->assertUnprocessable();
    }

    public function test_update_replaces_items(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $serviceA = Service::factory()->forTenant($tenant)->create(['name' => 'Serviço A']);
        $serviceB = Service::factory()->forTenant($tenant)->create(['name' => 'Serviço B']);
        $admin = $this->createAdmin($tenant);

        $order = ServiceOrder::factory()->forClient($client)->create(['number' => 30]);
        ServiceOrderItem::factory()->forOrder($order)->create([
            'service_id' => $serviceA->getKey(),
            'name' => $serviceA->name,
            'quantity' => 1,
            'unit_amount_cents' => 5000,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/service-orders/{$order->uuid}", [
            'items' => [[
                'service_id' => $serviceB->uuid,
                'quantity' => 3,
                'unit_amount_cents' => 2000,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Serviço B')
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items_total_cents', 6000);

        $this->assertDatabaseMissing('service_order_items', [
            'service_order_id' => $order->getKey(),
            'service_id' => $serviceA->getKey(),
        ]);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'action' => 'services_changed',
        ]);
    }

    public function test_generates_billing_from_order_items(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create([
            'name' => 'Manutenção preventiva',
        ]);
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 2,
                'unit_amount_cents' => 7500,
            ]],
        ])->assertCreated()->json('data.id');

        $billing = $this->postJson("/api/service-orders/{$orderId}/billing", [
            'due_at' => '2026-11-10',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount_cents', 15000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.service_order_id', $orderId)
            ->json('data');

        $order = ServiceOrder::query()->where('uuid', $orderId)->firstOrFail();
        $storedBilling = FinanceBilling::query()->where('uuid', $billing['id'])->firstOrFail();

        $this->assertSame($tenant->getKey(), $storedBilling->tenant_id);
        $this->assertSame($client->getKey(), $storedBilling->client_id);
        $this->assertSame($order->getKey(), $storedBilling->service_order_id);
        $this->assertSame(15000, $storedBilling->amount_cents);
        $this->assertSame('2026-11-10', $storedBilling->due_at?->toDateString());

        $this->getJson("/api/service-orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.billing.id', $billing['id'])
            ->assertJsonPath('data.billing.amount_cents', 15000);

        $this->postJson("/api/service-orders/{$orderId}/billing", [])
            ->assertUnprocessable();

        $this->assertDatabaseHas('service_order_histories', [
            'action' => 'billing_generated',
        ]);
    }

    public function test_billing_requires_items_and_blocks_cancelled_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $emptyOrder = ServiceOrder::factory()->forClient($client)->create(['number' => 40]);

        $this->postJson("/api/service-orders/{$emptyOrder->uuid}/billing", [])
            ->assertUnprocessable();

        $cancelledOrder = ServiceOrder::factory()->forClient($client)->create([
            'number' => 41,
            'status' => 'cancelled',
        ]);
        ServiceOrderItem::factory()->forOrder($cancelledOrder)->create([
            'service_id' => $service->getKey(),
            'name' => $service->name,
            'quantity' => 1,
            'unit_amount_cents' => 3000,
        ]);

        $this->postJson("/api/service-orders/{$cancelledOrder->uuid}/billing", [])
            ->assertUnprocessable();
    }

    public function test_billing_endpoint_requires_finance_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create();

        $order = ServiceOrder::factory()->forClient($client)->create(['number' => 42]);
        ServiceOrderItem::factory()->forOrder($order)->create([
            'service_id' => $service->getKey(),
            'name' => $service->name,
            'quantity' => 1,
            'unit_amount_cents' => 3000,
        ]);

        Sanctum::actingAs($this->createClient($tenant));

        $this->postJson("/api/service-orders/{$order->uuid}/billing", [])
            ->assertForbidden();
    }

    public function test_manages_order_photos(): void
    {
        Storage::fake('public');

        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $order = ServiceOrder::factory()->forClient($client)->create(['number' => 43]);

        Storage::disk('public')->put('uploads/os-foto.jpg', 'conteudo');

        $photoId = $this->postJson("/api/service-orders/{$order->uuid}/photos", [
            'path' => 'uploads/os-foto.jpg',
        ])
            ->assertCreated()
            ->assertJsonPath('data.path', 'uploads/os-foto.jpg')
            ->json('data.id');

        $this->getJson("/api/service-orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data.photos');

        $this->deleteJson("/api/service-orders/{$order->uuid}/photos/{$photoId}")
            ->assertOk();

        Storage::disk('public')->assertMissing('uploads/os-foto.jpg');
        $this->assertDatabaseCount('service_order_photos', 0);
    }

    public function test_cancelled_order_cannot_receive_photos(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        Sanctum::actingAs($this->createAdmin($tenant));

        $order = ServiceOrder::factory()->forClient($client)->create([
            'number' => 44,
            'status' => 'cancelled',
        ]);

        $this->postJson("/api/service-orders/{$order->uuid}/photos", [
            'path' => 'uploads/os-foto.jpg',
        ])->assertUnprocessable();
    }

    public function test_creates_order_with_initial_status_and_updates_status(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        Sanctum::actingAs($this->createAdmin($tenant));

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'status' => 'in_progress',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'in_progress')
            ->json('data.id');

        $this->signServiceOrder($orderId);

        $this->putJson("/api/service-orders/{$orderId}", [
            'status' => 'completed',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull(ServiceOrder::query()->where('uuid', $orderId)->value('completed_at'));

        $openOrder = ServiceOrder::factory()->forClient($client)->create(['number' => 45]);

        $this->putJson("/api/service-orders/{$openOrder->uuid}", [
            'status' => 'completed',
        ])->assertUnprocessable();

        $this->putJson("/api/service-orders/{$openOrder->uuid}", [
            'status' => 'cancelled',
        ])->assertUnprocessable();

        $this->putJson("/api/service-orders/{$openOrder->uuid}", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente desistiu',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Cliente desistiu');
    }

    public function test_create_rejects_invalid_initial_status(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'status' => 'completed',
        ])->assertUnprocessable();
    }
}
