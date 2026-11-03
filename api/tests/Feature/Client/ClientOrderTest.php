<?php

namespace Tests\Feature\Client;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientOrder;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientOrderTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_per_vehicle_plan_charges_plan_amount_times_selected_vehicles(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->perVehicle()->create([
            'name' => 'Rastreamento por veículo',
            'amount_cents' => 3000,
        ]);

        $cars = Vehicle::factory()->count(5)->forClient($client)->create();
        $selected = $cars->take(4);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'due_day' => 15,
            'plan_id' => $plan->uuid,
            'vehicle_ids' => $selected->pluck('uuid')->all(),
        ])
            ->assertOk()
            ->assertJsonPath('data.plan_id', $plan->uuid)
            ->assertJsonPath('data.pricing_type', 'per_vehicle')
            ->assertJsonPath('data.total_cents', 12000)
            ->assertJsonPath('data.total', '120.00')
            ->assertJsonPath('data.due_day', 15)
            ->assertJsonCount(4, 'data.vehicle_ids')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.items.0.unit_amount_cents', 3000);

        $subscription = FinanceSubscription::query()->where('client_id', $client->getKey())->firstOrFail();
        $this->assertSame($plan->getKey(), $subscription->plan_id);
        $this->assertSame(12000, $subscription->plan_price_cents);
        $this->assertSame('Rastreamento por veículo', $subscription->plan_name);
        $this->assertSame(15, $subscription->due_day);
        $this->assertNull($client->fresh()->plan_id);

        $this->assertTrue(
            FinanceBilling::query()->where('subscription_id', $subscription->getKey())->get()
                ->every(fn (FinanceBilling $billing): bool => $billing->amount_cents === 12000),
        );
    }

    public function test_fixed_plan_charges_plan_amount_and_ignores_vehicles(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create([
            'amount_cents' => 9900,
            'periodicity' => BillingPeriodicity::QUARTERLY,
        ]);
        $vehicles = Vehicle::factory()->count(3)->forClient($client)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'plan_id' => $plan->uuid,
            'vehicle_ids' => $vehicles->pluck('uuid')->all(),
        ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.total_cents', 9900)
            ->assertJsonPath('data.periodicity', 'quarterly')
            ->assertJsonCount(0, 'data.vehicle_ids');

        $subscription = FinanceSubscription::query()->where('client_id', $client->getKey())->firstOrFail();
        $this->assertSame(9900, $subscription->plan_price_cents);
        $this->assertSame(BillingPeriodicity::QUARTERLY, $subscription->plan_periodicity);
    }

    public function test_upsert_generates_first_two_billings_on_creation(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->perVehicle()->create(['amount_cents' => 5000]);
        $vehicle = Vehicle::factory()->forClient($client)->create();

        $firstDue = now()->startOfMonth()->addMonthNoOverflow()->day(10);
        $secondDue = $firstDue->copy()->addMonthNoOverflow();
        $thirdDue = $secondDue->copy()->addMonthNoOverflow();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'due_day' => 10,
            'next_billing_at' => $firstDue->toDateString(),
            'plan_id' => $plan->uuid,
            'vehicle_ids' => [$vehicle->uuid],
        ])->assertOk();

        $subscription = FinanceSubscription::query()->where('client_id', $client->getKey())->firstOrFail();

        $billings = FinanceBilling::query()
            ->where('subscription_id', $subscription->getKey())
            ->orderBy('due_at')
            ->get();

        $this->assertCount(2, $billings);
        $this->assertSame($firstDue->toDateString(), $billings[0]->due_at?->toDateString());
        $this->assertSame($secondDue->toDateString(), $billings[1]->due_at?->toDateString());
        $this->assertSame(5000, $billings[0]->amount_cents);
        $this->assertSame(5000, $billings[1]->amount_cents);
        $this->assertTrue(
            $billings->every(fn (FinanceBilling $billing): bool => $billing->status === BillingStatus::PENDING),
        );

        $this->assertSame($secondDue->toDateString(), $subscription->last_billed_at?->toDateString());
        $this->assertSame($thirdDue->toDateString(), $subscription->next_billing_at?->toDateString());
    }

    public function test_per_vehicle_plan_requires_vehicles(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->perVehicle()->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'plan_id' => $plan->uuid,
            'vehicle_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('vehicle_ids');
    }

    public function test_requires_plan(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_rejects_inactive_plan(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create(['is_active' => false]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'plan_id' => $plan->uuid,
        ])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    }

    public function test_rejects_vehicles_from_another_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $other = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->perVehicle()->create();
        $foreignVehicle = Vehicle::factory()->forClient($other)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'plan_id' => $plan->uuid,
            'vehicle_ids' => [$foreignVehicle->uuid],
        ])->assertUnprocessable()->assertJsonValidationErrors('vehicle_ids');
    }

    public function test_show_returns_null_when_client_has_no_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson("/api/clients/{$client->uuid}/order")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_updates_existing_order_totals(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->perVehicle()->create(['amount_cents' => 10000]);
        $first = Vehicle::factory()->forClient($client)->create();
        $second = Vehicle::factory()->forClient($client)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'plan_id' => $plan->uuid,
            'vehicle_ids' => [$first->uuid],
        ])->assertOk()->assertJsonPath('data.total_cents', 10000);

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'plan_id' => $plan->uuid,
            'vehicle_ids' => [$first->uuid, $second->uuid],
        ])->assertOk()->assertJsonPath('data.total_cents', 20000);

        $this->assertSame(1, ClientOrder::query()->where('client_id', $client->getKey())->count());
        $this->assertSame(1, FinanceSubscription::query()->where('client_id', $client->getKey())->count());
        $this->assertSame(20000, FinanceSubscription::query()->where('client_id', $client->getKey())->value('plan_price_cents'));

        // As cobranças iniciais são geradas apenas na criação do pedido.
        $this->assertSame(2, FinanceBilling::query()->count());
    }

    public function test_per_vehicle_plan_cannot_be_assigned_without_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->perVehicle()->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->patchJson("/api/clients/{$client->uuid}/plan", [
            'plan_id' => $plan->uuid,
        ])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    }

    public function test_plan_crud_persists_pricing_type(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $id = $this->postJson('/api/finance/plans', [
            'name' => 'Por veículo',
            'amount_cents' => 3000,
            'pricing_type' => 'per_vehicle',
        ])
            ->assertCreated()
            ->assertJsonPath('data.pricing_type', 'per_vehicle')
            ->assertJsonPath('data.pricing_type_label', 'Por veículo')
            ->json('data.id');

        $this->patchJson("/api/finance/plans/{$id}", ['pricing_type' => 'fixed'])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'fixed');

        $this->postJson('/api/finance/plans', [
            'name' => 'Padrão',
            'amount_cents' => 1000,
        ])->assertCreated()->assertJsonPath('data.pricing_type', 'fixed');
    }
}
