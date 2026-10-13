<?php

namespace Tests\Feature\Client;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientOrder;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Service\Models\Service;
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

    public function test_upsert_creates_order_and_subscription_from_services_and_vehicles(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $tracker = Service::factory()->forTenant($tenant)->create([
            'name' => 'Instalação de rastreador',
            'amount_cents' => 5000,
        ]);
        $blocker = Service::factory()->forTenant($tenant)->create([
            'name' => 'Instalação de bloqueador',
            'amount_cents' => 3000,
        ]);

        $cars = Vehicle::factory()->count(3)->forClient($client)->create();
        $blockerCars = $cars->take(2);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'due_day' => 15,
            'periodicity' => 'monthly',
            'items' => [
                [
                    'service_id' => $tracker->uuid,
                    'vehicle_ids' => $cars->pluck('uuid')->all(),
                ],
                [
                    'service_id' => $blocker->uuid,
                    'vehicle_ids' => $blockerCars->pluck('uuid')->all(),
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.total_cents', 21000)
            ->assertJsonPath('data.total', '210.00')
            ->assertJsonPath('data.due_day', 15)
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('client_orders', [
            'client_id' => $client->getKey(),
            'total_cents' => 21000,
            'due_day' => 15,
        ]);

        $subscription = FinanceSubscription::query()->where('client_id', $client->getKey())->first();
        $this->assertNotNull($subscription);
        $this->assertNull($subscription->plan_id);
        $this->assertSame(21000, $subscription->plan_price_cents);
        $this->assertSame(15, $subscription->due_day);
        $this->assertStringContainsString('rastreador', (string) $subscription->plan_name);
    }

    public function test_upsert_generates_first_two_billings_on_creation(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create(['amount_cents' => 5000]);
        $vehicle = Vehicle::factory()->forClient($client)->create();

        $firstDue = now()->startOfMonth()->addMonthNoOverflow()->day(10);
        $secondDue = $firstDue->copy()->addMonthNoOverflow();
        $thirdDue = $secondDue->copy()->addMonthNoOverflow();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'due_day' => 10,
            'periodicity' => 'monthly',
            'next_billing_at' => $firstDue->toDateString(),
            'items' => [
                ['service_id' => $service->uuid, 'vehicle_ids' => [$vehicle->uuid]],
            ],
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

    public function test_rejects_vehicles_from_another_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $other = Client::factory()->for($tenant)->create();
        $service = Service::factory()->forTenant($tenant)->create(['amount_cents' => 1000]);
        $foreignVehicle = Vehicle::factory()->forClient($other)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'items' => [
                [
                    'service_id' => $service->uuid,
                    'vehicle_ids' => [$foreignVehicle->uuid],
                ],
            ],
        ])->assertUnprocessable();
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
        $service = Service::factory()->forTenant($tenant)->create(['amount_cents' => 10000]);
        $first = Vehicle::factory()->forClient($client)->create();
        $second = Vehicle::factory()->forClient($client)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'items' => [
                ['service_id' => $service->uuid, 'vehicle_ids' => [$first->uuid]],
            ],
        ])->assertOk()->assertJsonPath('data.total_cents', 10000);

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'items' => [
                ['service_id' => $service->uuid, 'vehicle_ids' => [$first->uuid, $second->uuid]],
            ],
        ])->assertOk()->assertJsonPath('data.total_cents', 20000);

        $this->assertSame(1, ClientOrder::query()->where('client_id', $client->getKey())->count());
        $this->assertSame(1, FinanceSubscription::query()->where('client_id', $client->getKey())->count());
        $this->assertSame(20000, FinanceSubscription::query()->where('client_id', $client->getKey())->value('plan_price_cents'));

        // As cobranças iniciais são geradas apenas na criação do pedido.
        $this->assertSame(2, FinanceBilling::query()->count());
    }
}
