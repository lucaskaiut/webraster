<?php

namespace Tests\Feature\Commission;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\Commission\Models\Commission;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CommissionTest extends TestCase
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

    public function test_service_accepts_percentage_and_fixed_commission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/services', [
            'name' => 'Instalação',
            'amount_cents' => 10000,
            'commission_type' => 'percentage',
            'commission_value' => 1000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.commission_type', 'percentage')
            ->assertJsonPath('data.commission_value', 1000);

        $this->postJson('/api/services', [
            'name' => 'Retirada',
            'amount_cents' => 10000,
            'commission_type' => 'fixed',
            'commission_value' => 2500,
        ])
            ->assertCreated()
            ->assertJsonPath('data.commission_type', 'fixed')
            ->assertJsonPath('data.commission_value', 2500);

        $this->postJson('/api/services', [
            'name' => 'Sem comissão',
            'amount_cents' => 10000,
            'commission_type' => null,
            'commission_value' => null,
        ])
            ->assertCreated()
            ->assertJsonPath('data.commission_type', null)
            ->assertJsonPath('data.commission_value', 0);

        $this->postJson('/api/services', [
            'name' => 'Percentual inválido',
            'amount_cents' => 10000,
            'commission_type' => 'percentage',
            'commission_value' => 10001,
        ])->assertUnprocessable()->assertJsonValidationErrors('commission_value');
    }

    public function test_creating_order_creates_forecast_commission_snapshot(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $technician = $this->createMember($tenant);
        $service = Service::factory()->forTenant($tenant)->create([
            'name' => 'Instalação',
            'amount_cents' => 10000,
            'commission_type' => 'percentage',
            'commission_value' => 1000,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 2,
                'unit_amount_cents' => 10000,
            ]],
        ])->assertCreated()->json('data.id');

        $order = ServiceOrder::query()->where('uuid', $orderId)->firstOrFail();

        $this->assertDatabaseHas('commissions', [
            'tenant_id' => $tenant->getKey(),
            'service_order_id' => $order->getKey(),
            'technician_id' => $technician->getKey(),
            'status' => 'forecast',
            'base_amount_cents' => 20000,
            'amount_cents' => 2000,
            'finalized_at' => null,
        ]);

        // Alterações futuras no cadastro do serviço não recalculam o snapshot.
        $service->update(['commission_value' => 5000]);

        $this->putJson("/api/service-orders/{$orderId}", [
            'notes' => 'Observação atualizada',
        ])->assertOk();

        $this->assertDatabaseHas('commissions', [
            'service_order_id' => $order->getKey(),
            'amount_cents' => 2000,
        ]);
    }

    public function test_every_new_order_creates_a_commission_record(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
        ])->assertCreated()->json('data.id');

        $order = ServiceOrder::query()->where('uuid', $orderId)->firstOrFail();

        $this->assertDatabaseHas('commissions', [
            'tenant_id' => $tenant->getKey(),
            'service_order_id' => $order->getKey(),
            'status' => 'forecast',
            'base_amount_cents' => 0,
            'amount_cents' => 0,
        ]);
    }

    public function test_updating_order_items_updates_forecast_commission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $technician = $this->createMember($tenant);
        $service = Service::factory()->forTenant($tenant)->create([
            'name' => 'Instalação',
            'amount_cents' => 1000,
            'commission_type' => 'fixed',
            'commission_value' => 500,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 1,
                'unit_amount_cents' => 1000,
            ]],
        ])->assertCreated()->json('data.id');

        $order = ServiceOrder::query()->where('uuid', $orderId)->firstOrFail();
        $this->assertSame(500, (int) $order->commission?->amount_cents);

        $this->putJson("/api/service-orders/{$orderId}", [
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 3,
                'unit_amount_cents' => 1000,
            ]],
        ])->assertOk();

        $commission = Commission::query()->where('service_order_id', $order->getKey())->firstOrFail();

        $this->assertSame(3000, $commission->base_amount_cents);
        $this->assertSame(1500, $commission->amount_cents);
        $this->assertSame('forecast', $commission->status->value);
    }

    public function test_completing_order_finalizes_commission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $technician = $this->createMember($tenant);
        $service = Service::factory()->forTenant($tenant)->create([
            'name' => 'Manutenção',
            'amount_cents' => 10000,
            'commission_type' => 'percentage',
            'commission_value' => 500,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'status' => 'in_progress',
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 1,
                'unit_amount_cents' => 10000,
            ]],
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/service-orders/{$orderId}/status", [
            'status' => 'completed',
        ])->assertOk();

        $order = ServiceOrder::query()->where('uuid', $orderId)->firstOrFail();
        $commission = Commission::query()->where('service_order_id', $order->getKey())->firstOrFail();

        $this->assertSame('finalized', $commission->status->value);
        $this->assertSame(500, $commission->amount_cents);
        $this->assertNotNull($commission->finalized_at);
    }

    public function test_cancelled_order_removes_commission_from_visible_list(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $technician = $this->createMember($tenant);
        $service = Service::factory()->forTenant($tenant)->create([
            'commission_type' => 'fixed',
            'commission_value' => 1000,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'unit_amount_cents' => 5000,
            ]],
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/service-orders/{$orderId}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente desistiu',
        ])->assertOk();

        $order = ServiceOrder::query()->where('uuid', $orderId)->firstOrFail();
        $commission = Commission::query()->where('service_order_id', $order->getKey())->firstOrFail();

        $this->assertSame('cancelled', $commission->status->value);

        $this->getJson('/api/commissions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_list_restricts_to_own_commissions_without_view_others_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $viewer = $this->createMember($tenant);
        $otherTechnician = $this->createMember($tenant);
        $viewer->roles()->firstOrFail()->revokePermissions(Permission::COMMISSION_VIEW_OTHERS);

        $service = Service::factory()->forTenant($tenant)->create([
            'commission_type' => 'fixed',
            'commission_value' => 1000,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $ownOrderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $viewer->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'unit_amount_cents' => 5000,
            ]],
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $otherTechnician->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'unit_amount_cents' => 9000,
            ]],
        ])->assertCreated();

        Sanctum::actingAs($viewer);

        $this->getJson('/api/commissions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service_order.id', $ownOrderId)
            ->assertJsonPath('data.0.technician.id', $viewer->uuid);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/commissions')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_requires_commission_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createClient($tenant));

        $this->getJson('/api/commissions')->assertForbidden();
    }

    public function test_order_detail_exposes_absolute_commission_only_with_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createMember($tenant);
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $service = Service::factory()->forTenant($tenant)->create([
            'commission_type' => 'fixed',
            'commission_value' => 2500,
        ]);

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 1,
                'unit_amount_cents' => 10000,
            ]],
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/service-orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.commission.amount_cents', 2500)
            ->assertJsonPath('data.commission.amount', '25.00')
            ->assertJsonMissingPath('data.commission.commission_type')
            ->assertJsonMissingPath('data.commission.commission_value');

        $admin->roles()->firstOrFail()->revokePermissions(Permission::COMMISSION_READ);
        $admin->unsetRelation('roles');

        $this->getJson("/api/service-orders/{$orderId}")
            ->assertOk()
            ->assertJsonMissingPath('data.commission');
    }

    public function test_technician_sees_only_own_absolute_commission_and_not_order_total(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $viewer = $this->createMember($tenant);
        $otherTechnician = $this->createMember($tenant);
        $viewer->roles()->firstOrFail()->revokePermissions(Permission::COMMISSION_VIEW_OTHERS);
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $service = Service::factory()->forTenant($tenant)->create([
            'commission_type' => 'percentage',
            'commission_value' => 1000,
        ]);

        $ownOrderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $viewer->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 2,
                'unit_amount_cents' => 10000,
            ]],
        ])->assertCreated()->json('data.id');

        $otherOrderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $otherTechnician->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 1,
                'unit_amount_cents' => 10000,
            ]],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($viewer);

        $this->getJson("/api/service-orders/{$ownOrderId}")
            ->assertOk()
            ->assertJsonPath('data.commission.amount_cents', 2000);

        $this->getJson("/api/service-orders/{$otherOrderId}")
            ->assertOk()
            ->assertJsonMissingPath('data.commission');

        $technician = $this->createTechnician($tenant);
        Sanctum::actingAs($admin);

        $technicianOrderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 1,
                'unit_amount_cents' => 10000,
            ]],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($technician);

        $this->getJson("/api/technician/service-orders/{$technicianOrderId}")
            ->assertOk()
            ->assertJsonPath('data.commission.amount_cents', 1000)
            ->assertJsonMissingPath('data.items_total_cents')
            ->assertJsonMissingPath('data.items_total');

        $technician->roles()->firstOrFail()->revokePermissions(Permission::COMMISSION_READ);
        $technician->unsetRelation('roles');

        $this->getJson("/api/technician/service-orders/{$technicianOrderId}")
            ->assertOk()
            ->assertJsonMissingPath('data.commission');
    }

    private function createTechnician(Tenant $tenant): User
    {
        $technician = User::factory()->for($tenant)->create([
            'type' => UserType::TECHNICIAN->value,
        ]);

        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        return $technician;
    }
}
