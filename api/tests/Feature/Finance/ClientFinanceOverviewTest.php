<?php

namespace Tests\Feature\Finance;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Shared\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientFinanceOverviewTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_client_finance_overview_returns_subscription_and_open_charge(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create(['name' => 'Empresarial']);
        $client->forceFill(['plan_id' => $plan->getKey()])->save();

        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create([
            'status' => SubscriptionStatus::ACTIVE,
            'next_billing_at' => now()->addDays(5)->toDateString(),
            'due_day' => 15,
        ]);
        $open = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::AWAITING_PAYMENT,
            'due_at' => now()->addDays(3)->toDateString(),
        ]);
        FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 2,
            'status' => BillingStatus::PAID,
            'due_at' => now()->subMonth()->toDateString(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson("/api/finance/clients/{$client->uuid}/overview")
            ->assertOk()
            ->assertJsonPath('data.plan.name', 'Empresarial')
            ->assertJsonPath('data.subscription.id', $subscription->uuid)
            ->assertJsonPath('data.open_billing.id', $open->uuid)
            ->assertJsonCount(2, 'data.billings');
    }

    public function test_updates_subscription_next_billing_and_due_day(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create([
            'status' => SubscriptionStatus::ACTIVE,
            'due_day' => 10,
            'next_billing_at' => now()->toDateString(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $next = now()->addDays(12)->toDateString();

        $this->patchJson("/api/finance/subscriptions/{$subscription->uuid}", [
            'next_billing_at' => $next,
            'due_day' => 20,
        ])
            ->assertOk()
            ->assertJsonPath('data.next_billing_at', $next)
            ->assertJsonPath('data.due_day', 20);
    }
}
