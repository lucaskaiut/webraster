<?php

namespace Tests\Feature\Finance;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Contracts\DeviceSuspensionProvider;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Models\FinanceWebhookLog;
use App\Modules\Finance\Models\TenantPaymentGatewayConfig;
use App\Modules\Finance\Services\DelinquencyService;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Shared\Subscription\Enums\SubscriptionStatus;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FinanceModuleTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_assigns_plan_and_creates_subscription_snapshot(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $plan = $this->postJson('/api/finance/plans', [
            'name' => 'Básico',
            'description' => 'Até 5 dispositivos',
            'amount_cents' => 4990,
            'periodicity' => 'monthly',
            'device_limit' => 5,
            'is_active' => true,
        ])->assertCreated()->json('data');

        $this->postJson('/api/finance/subscriptions/assign', [
            'client_id' => $client->uuid,
            'plan_id' => $plan['id'],
            'due_day' => 10,
            'block_on_overdue' => true,
            'block_after_days' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan_name', 'Básico')
            ->assertJsonPath('data.plan_price_cents', 4990)
            ->assertJsonPath('data.plan_periodicity', 'monthly');

        $this->assertSame($plan['id'], $client->fresh()->plan?->uuid);
    }

    public function test_patch_client_plan_upserts_subscription(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create(['name' => 'Pro']);
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->patchJson("/api/clients/{$client->uuid}/plan", [
            'plan_id' => $plan->uuid,
            'due_day' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('data.plan_name', 'Pro')
            ->assertJsonPath('data.due_day', 15);

        $this->assertDatabaseHas('finance_subscriptions', [
            'client_id' => $client->getKey(),
            'plan_id' => $plan->getKey(),
            'plan_name' => 'Pro',
        ]);
    }

    public function test_generates_billing_for_subscription(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create([
            'status' => SubscriptionStatus::ACTIVE,
            'next_billing_at' => now()->toDateString(),
        ]);

        $this->postJson('/api/finance/billings', [
            'subscription_id' => $subscription->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'BILL-000001')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_tenant_isolation_for_plans(): void
    {
        [, $tenantA] = $this->createOperationalChild();
        [, $tenantB] = $this->createOperationalChild();
        FinancePlan::factory()->forTenant($tenantA)->create(['name' => 'Plano A']);

        Sanctum::actingAs($this->createAdmin($tenantB));
        $this->getJson('/api/finance/plans')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_forbids_plan_create_without_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createClient($tenant));

        $this->postJson('/api/finance/plans', [
            'name' => 'X',
            'amount_cents' => 1000,
            'periodicity' => 'monthly',
        ])->assertForbidden();
    }

    public function test_webhook_marks_billing_paid_idempotently(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::AWAITING_PAYMENT,
            'gateway_payment_id' => 'pay_123',
        ]);

        $this->createGatewayConfig($tenant, 'secret-token');
        $this->fakeAsaasPayment('pay_123', 'RECEIVED', $billing->uuid, 49.90);

        $payload = [
            'id' => 'evt_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_123',
                'value' => 49.90,
                'status' => 'RECEIVED',
            ],
        ];

        $this->postJson("/api/webhooks/payments/asaas/{$tenant->uuid}", $payload, [
            'asaas-access-token' => 'secret-token',
        ])->assertOk();

        $this->assertSame(BillingStatus::PAID, $billing->fresh()->status);

        $this->postJson("/api/webhooks/payments/asaas/{$tenant->uuid}", $payload, [
            'asaas-access-token' => 'secret-token',
        ])->assertOk();

        $this->assertSame(1, FinanceWebhookLog::query()->where('event_id', 'evt_1')->count());
    }

    public function test_webhook_does_not_mark_paid_when_gateway_status_is_pending(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::AWAITING_PAYMENT,
            'gateway_payment_id' => 'pay_pending',
        ]);

        $this->createGatewayConfig($tenant, 'secret-token');
        $this->fakeAsaasPayment('pay_pending', 'PENDING', $billing->uuid, 49.90);

        $this->postJson("/api/webhooks/payments/asaas/{$tenant->uuid}", [
            'id' => 'evt_fake_paid',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_pending',
                'value' => 49.90,
                'status' => 'RECEIVED',
            ],
        ], [
            'asaas-access-token' => 'secret-token',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_gateway']);

        $this->assertSame(BillingStatus::AWAITING_PAYMENT, $billing->fresh()->status);
        $this->assertSame(
            'failed',
            FinanceWebhookLog::query()->where('event_id', 'evt_fake_paid')->value('status'),
        );
    }

    public function test_webhook_does_not_mark_paid_when_gateway_payment_is_missing(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::AWAITING_PAYMENT,
            'gateway_payment_id' => 'pay_missing',
        ]);

        $this->createGatewayConfig($tenant, 'secret-token');
        Http::fake([
            '*/payments/pay_missing' => Http::response([
                'errors' => [['code' => 'invalid_id', 'description' => 'Payment not found.']],
            ], 404),
        ]);

        $this->postJson("/api/webhooks/payments/asaas/{$tenant->uuid}", [
            'id' => 'evt_missing',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_missing',
                'value' => 49.90,
                'status' => 'RECEIVED',
            ],
        ], [
            'asaas-access-token' => 'secret-token',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_gateway']);

        $this->assertSame(BillingStatus::AWAITING_PAYMENT, $billing->fresh()->status);
    }

    public function test_delinquency_suspends_and_payment_unsuspends(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create([
            'vehicle_id' => $vehicle->getKey(),
            'billing_suspended_at' => null,
        ]);

        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create([
            'block_on_overdue' => true,
            'block_after_days' => 5,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::OVERDUE,
            'due_at' => now()->subDays(6)->toDateString(),
        ]);

        $fakeProvider = new class implements DeviceSuspensionProvider
        {
            public array $suspended = [];

            public array $unsuspended = [];

            public function suspend(Equipment $equipment): void
            {
                $equipment->forceFill(['billing_suspended_at' => now()])->save();
                $this->suspended[] = $equipment->getKey();
            }

            public function unsuspend(Equipment $equipment): void
            {
                $equipment->forceFill(['billing_suspended_at' => null])->save();
                $this->unsuspended[] = $equipment->getKey();
            }
        };

        $this->app->instance(DeviceSuspensionProvider::class, $fakeProvider);

        app(DelinquencyService::class)->process();

        $this->assertNotNull($equipment->fresh()->billing_suspended_at);

        Sanctum::actingAs($this->createAdmin($tenant));
        $this->postJson("/api/finance/billings/{$billing->uuid}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertNull($equipment->fresh()->billing_suspended_at);
    }

    public function test_portal_lists_only_own_billings(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subA = FinanceSubscription::factory()->forClient($clientA, $plan)->create();
        $subB = FinanceSubscription::factory()->forClient($clientB, $plan)->create();
        FinanceBilling::factory()->forSubscription($subA)->create(['number' => 1]);
        FinanceBilling::factory()->forSubscription($subB)->create(['number' => 2]);

        $user = $this->createClient($tenant);
        $user->forceFill(['client_id' => $clientA->getKey()])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/finance/portal/billings')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_dashboard_returns_metrics(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/finance/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'mrr_cents',
                    'arr_cents',
                    'active_clients',
                    'delinquent_clients',
                    'month_revenue_received_cents',
                    'month_expected_cents',
                    'open_amount_cents',
                ],
            ]);
    }

    public function test_charge_requires_gateway_config(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create([
            'document' => '39053344705',
            'email' => 'cliente@example.com',
        ]);
        Sanctum::actingAs($this->createAdmin($tenant));

        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
        ]);

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'pix',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_gateway']);
    }

    public function test_charge_creates_gateway_payment(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create([
            'document' => '39053344705',
            'email' => 'cliente@example.com',
        ]);
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $this->createGatewayConfig($tenant, 'wh_token', 'asaas_key');

        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
        ]);

        Http::fake([
            '*/customers*' => Http::response(['id' => 'cus_1', 'name' => 'Cliente'], 200),
            '*/payments' => Http::response([
                'id' => 'pay_abc',
                'invoiceUrl' => 'https://asaas.test/i/1',
                'bankSlipUrl' => 'https://asaas.test/b/1',
                'status' => 'PENDING',
            ], 200),
        ]);

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.gateway_payment_id', 'pay_abc')
            ->assertJsonPath('data.payment_gateway', 'asaas');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments')
            && $request['dueDate'] === $billing->due_at->toDateString());
    }

    public function test_charge_uses_today_as_due_date_when_billing_is_overdue(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create([
            'document' => '39053344705',
            'email' => 'cliente@example.com',
        ]);
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->createGatewayConfig($tenant, 'wh_token', 'asaas_key');

        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
            'due_at' => now()->subDays(10)->toDateString(),
        ]);

        Http::fake([
            '*/customers*' => Http::response(['id' => 'cus_1', 'name' => 'Cliente'], 200),
            '*/payments' => Http::response([
                'id' => 'pay_overdue',
                'status' => 'PENDING',
            ], 200),
        ]);

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.gateway_payment_id', 'pay_overdue');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments')
            && $request['dueDate'] === now()->toDateString());
    }

    public function test_reactivate_unsuspends_devices_even_when_overdue(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create([
            'vehicle_id' => $vehicle->getKey(),
            'billing_suspended_at' => now(),
        ]);
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create([
            'status' => SubscriptionStatus::SUSPENDED,
        ]);
        FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::OVERDUE,
            'due_at' => now()->subDays(10)->toDateString(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/finance/subscriptions/{$subscription->uuid}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertNull($equipment->fresh()->billing_suspended_at);
    }

    private function fakeAsaasPayment(
        string $paymentId,
        string $status,
        string $externalReference,
        float $value,
    ): void {
        Http::fake([
            '*/payments/'.$paymentId => Http::response([
                'id' => $paymentId,
                'status' => $status,
                'value' => $value,
                'externalReference' => $externalReference,
            ], 200),
        ]);
    }

    private function createGatewayConfig(
        Tenant $tenant,
        string $webhookToken = 'secret-token',
        string $apiKey = 'test_key',
    ): TenantPaymentGatewayConfig {
        $config = new TenantPaymentGatewayConfig;
        $config->forceFill([
            'tenant_id' => $tenant->getKey(),
            'gateway' => 'asaas',
            'is_active' => true,
            'credentials' => [
                'environment' => 'sandbox',
                'api_key' => $apiKey,
                'webhook_token' => $webhookToken,
            ],
        ]);
        $config->save();

        return $config;
    }
}
