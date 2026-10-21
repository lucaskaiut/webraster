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
use Carbon\CarbonImmutable;
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
            ->assertJsonPath('data.code', '1')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_generates_billing_thirty_days_before_due_date(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();

        $dueSoon = now()->addDays(30)->toDateString();
        $dueLater = now()->addDays(31)->toDateString();

        $subscription = FinanceSubscription::factory()
            ->forClient(Client::factory()->for($tenant)->create(), $plan)
            ->create([
                'status' => SubscriptionStatus::ACTIVE,
                'next_billing_at' => $dueSoon,
            ]);

        $notYetDue = FinanceSubscription::factory()
            ->forClient(Client::factory()->for($tenant)->create(), $plan)
            ->create([
                'status' => SubscriptionStatus::ACTIVE,
                'next_billing_at' => $dueLater,
            ]);

        $this->artisan('finance:generate-billings')->assertSuccessful();

        $this->assertTrue(
            $subscription->billings()->whereDate('due_at', $dueSoon)->exists(),
        );

        $this->assertFalse(
            $notYetDue->billings()->exists(),
        );
    }

    public function test_generates_next_billing_from_last_billing(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $lastDue = now()->addDays(10)->startOfDay();
        $expectedDue = $lastDue->copy()->addMonthNoOverflow();

        $subscription = FinanceSubscription::factory()->forClient($client)->create([
            'status' => SubscriptionStatus::ACTIVE,
            'plan_periodicity' => 'monthly',
            'plan_price_cents' => 4990,
            'next_billing_at' => $expectedDue->toDateString(),
        ]);

        FinanceBilling::factory()->forSubscription($subscription)->create([
            'due_at' => $lastDue->toDateString(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/finance/subscriptions/{$subscription->uuid}/next-billing")
            ->assertCreated()
            ->assertJsonPath('data.due_at', $expectedDue->toDateString())
            ->assertJsonPath('data.amount_cents', 4990)
            ->assertJsonPath('data.status', 'pending');

        $subscription->refresh();

        $this->assertSame($expectedDue->toDateString(), $subscription->last_billed_at?->toDateString());
        $this->assertSame(
            $expectedDue->copy()->addMonthNoOverflow()->toDateString(),
            $subscription->next_billing_at?->toDateString(),
        );
        $this->assertSame(
            2,
            FinanceBilling::query()->where('subscription_id', $subscription->getKey())->count(),
        );
    }

    public function test_generates_next_billing_from_next_billing_at_without_billings(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $due = now()->addDays(15)->toDateString();

        $subscription = FinanceSubscription::factory()->forClient($client)->create([
            'status' => SubscriptionStatus::ACTIVE,
            'next_billing_at' => $due,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/finance/subscriptions/{$subscription->uuid}/next-billing")
            ->assertCreated()
            ->assertJsonPath('data.due_at', $due);
    }

    public function test_generates_next_billing_respecting_later_scheduled_date(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $lastDue = now()->addDays(5)->startOfDay();
        $scheduled = $lastDue->copy()->addMonthNoOverflow()->addDays(7);

        $subscription = FinanceSubscription::factory()->forClient($client)->create([
            'status' => SubscriptionStatus::ACTIVE,
            'plan_periodicity' => 'monthly',
            'next_billing_at' => $scheduled->toDateString(),
        ]);

        FinanceBilling::factory()->forSubscription($subscription)->create([
            'due_at' => $lastDue->toDateString(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/finance/subscriptions/{$subscription->uuid}/next-billing")
            ->assertCreated()
            ->assertJsonPath('data.due_at', $scheduled->toDateString());
    }

    public function test_client_role_without_billing_create_permission_cannot_generate_next_billing(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $subscription = FinanceSubscription::factory()->forClient($client)->create([
            'status' => SubscriptionStatus::ACTIVE,
            'next_billing_at' => now()->addDays(15)->toDateString(),
        ]);

        Sanctum::actingAs($this->createClient($tenant));

        $this->postJson("/api/finance/subscriptions/{$subscription->uuid}/next-billing")
            ->assertForbidden();
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

    public function test_webhook_accepts_payment_from_an_earlier_gateway_charge(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::AWAITING_PAYMENT,
            'gateway_payment_id' => 'pay_new',
            'gateway_expires_at' => now()->subMinute(),
        ]);

        $this->createGatewayConfig($tenant, 'secret-token');
        $this->fakeAsaasPayment('pay_old', 'RECEIVED', $billing->uuid, 49.90);

        // Pagamento da cobrança anterior (já expirada) da mesma fatura.
        $this->postJson("/api/webhooks/payments/asaas/{$tenant->uuid}", [
            'id' => 'evt_old_payment',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_old',
                'value' => 49.90,
                'status' => 'RECEIVED',
                'externalReference' => $billing->uuid,
            ],
        ], [
            'asaas-access-token' => 'secret-token',
        ])->assertOk();

        $this->assertSame(BillingStatus::PAID, $billing->fresh()->status);
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

        $response = $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.gateway_payment_id', 'pay_abc')
            ->assertJsonPath('data.payment_gateway', 'asaas');

        $this->assertNotNull($response->json('data.gateway_expires_at'));

        // A cobrança no gateway tem validade curta (hoje), não o vencimento da fatura.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments')
            && $request['dueDate'] === now()->toDateString());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/customers')
            && $request['notificationDisabled'] === true);
    }

    public function test_charge_uses_short_gateway_expiration(): void
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
        // O vencimento contratual está distante: prevalece a validade curta do gateway.
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
            'due_at' => now()->addDays(30)->toDateString(),
        ]);

        Http::fake([
            '*/customers*' => Http::response(['id' => 'cus_1', 'name' => 'Cliente'], 200),
            '*/payments' => Http::response([
                'id' => 'pay_short',
                'status' => 'PENDING',
            ], 200),
        ]);

        $response = $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments')
            && $request['dueDate'] === now()->toDateString());

        $expiresAt = CarbonImmutable::parse((string) $response->json('data.gateway_expires_at'));

        $this->assertTrue(
            $expiresAt->between(
                CarbonImmutable::now()->addMinutes(25),
                CarbonImmutable::now()->addMinutes(35),
            ),
            'A cobrança no gateway deve expirar em aproximadamente 30 minutos.',
        );
    }

    public function test_charge_regenerates_gateway_payment_after_expiration(): void
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
        ]);

        Http::fake([
            '*/customers*' => Http::response(['id' => 'cus_1', 'name' => 'Cliente'], 200),
            '*/payments' => Http::sequence()
                ->push(['id' => 'pay_first', 'status' => 'PENDING'], 200)
                ->push(['id' => 'pay_second', 'status' => 'PENDING'], 200),
        ]);

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.gateway_payment_id', 'pay_first');

        // Simula a expiração da cobrança gerada no gateway.
        $billing->forceFill(['gateway_expires_at' => now()->subMinute()])->save();

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.gateway_payment_id', 'pay_second');

        $this->assertTrue($billing->fresh()->gateway_expires_at->isFuture());
    }

    public function test_charge_reuses_gateway_payment_before_expiration(): void
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
        ]);

        Http::fake([
            '*/customers*' => Http::response(['id' => 'cus_1', 'name' => 'Cliente'], 200),
            '*/payments' => Http::response([
                'id' => 'pay_single',
                'status' => 'PENDING',
            ], 200),
        ]);

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.gateway_payment_id', 'pay_single');

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.gateway_payment_id', 'pay_single');

        $paymentsCreated = Http::recorded(
            fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/payments'),
        );

        $this->assertCount(1, $paymentsCreated);
    }

    public function test_charge_applies_late_fees_to_overdue_billing(): void
    {
        [, $tenant] = $this->createOperationalChild(childAttributes: [
            'late_fine_percent' => 2,
            'late_interest_percent' => 1,
        ]);
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
            'amount_cents' => 5000,
            'due_at' => now()->subDays(10)->toDateString(),
        ]);

        Http::fake([
            '*/customers*' => Http::response(['id' => 'cus_1', 'name' => 'Cliente'], 200),
            '*/payments' => Http::response([
                'id' => 'pay_late',
                'status' => 'PENDING',
            ], 200),
        ]);

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.fine_cents', 100)
            ->assertJsonPath('data.interest_cents', 17)
            ->assertJsonPath('data.total_cents', 5117)
            ->assertJsonPath('data.total', '51.17');

        // Multa 2% (R$ 50 → R$ 1,00) + juros 1% ao mês por 10 dias (R$ 0,17).
        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments')
            && (float) $request['value'] === 51.17);
    }

    public function test_charge_does_not_apply_late_fees_before_due_date(): void
    {
        [, $tenant] = $this->createOperationalChild(childAttributes: [
            'late_fine_percent' => 2,
            'late_interest_percent' => 1,
        ]);
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
            'amount_cents' => 5000,
            'due_at' => now()->addDays(5)->toDateString(),
        ]);

        Http::fake([
            '*/customers*' => Http::response(['id' => 'cus_1', 'name' => 'Cliente'], 200),
            '*/payments' => Http::response([
                'id' => 'pay_ontime',
                'status' => 'PENDING',
            ], 200),
        ]);

        $this->postJson("/api/finance/billings/{$billing->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.fine_cents', 0)
            ->assertJsonPath('data.interest_cents', 0)
            ->assertJsonPath('data.total_cents', 5000);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments')
            && (float) $request['value'] === 50.0);
    }

    public function test_portal_billing_show_applies_late_fees(): void
    {
        [, $tenant] = $this->createOperationalChild(childAttributes: [
            'late_fine_percent' => 2,
            'late_interest_percent' => 1,
        ]);
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
            'amount_cents' => 5000,
            'due_at' => now()->subDays(10)->toDateString(),
        ]);

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $this->getJson("/api/finance/portal/billings/{$billing->uuid}")
            ->assertOk()
            ->assertJsonPath('data.fine_cents', 100)
            ->assertJsonPath('data.interest_cents', 17)
            ->assertJsonPath('data.total_cents', 5117)
            ->assertJsonPath('data.total', '51.17');

        $this->assertDatabaseHas('finance_billings', [
            'id' => $billing->getKey(),
            'fine_cents' => 100,
            'interest_cents' => 17,
        ]);
    }

    public function test_mark_paid_uses_total_with_late_fees(): void
    {
        [, $tenant] = $this->createOperationalChild(childAttributes: [
            'late_fine_percent' => 2,
            'late_interest_percent' => 1,
        ]);
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
            'amount_cents' => 5000,
            'due_at' => now()->subDays(10)->toDateString(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/finance/billings/{$billing->uuid}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_amount_cents', 5117);
    }

    public function test_deletes_open_billing_with_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->deleteJson("/api/finance/billings/{$billing->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Cobrança excluída.');

        $this->assertSoftDeleted('finance_billings', ['id' => $billing->getKey()]);
        $this->assertDatabaseHas('finance_billing_events', [
            'billing_id' => $billing->getKey(),
            'action' => 'deleted',
        ]);
    }

    public function test_cannot_delete_billing_without_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
        ]);

        Sanctum::actingAs($this->createClient($tenant));

        $this->deleteJson("/api/finance/billings/{$billing->uuid}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('finance_billings', ['id' => $billing->getKey()]);
    }

    public function test_cannot_delete_paid_billing(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PAID,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->deleteJson("/api/finance/billings/{$billing->uuid}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertNotSoftDeleted('finance_billings', ['id' => $billing->getKey()]);
    }

    public function test_cannot_delete_billing_with_active_gateway_payment(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::AWAITING_PAYMENT,
            'gateway_payment_id' => 'pay_active',
            'gateway_expires_at' => now()->addMinutes(30),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->deleteJson("/api/finance/billings/{$billing->uuid}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertNotSoftDeleted('finance_billings', ['id' => $billing->getKey()]);
    }

    public function test_cannot_delete_billing_from_another_tenant(): void
    {
        [, $tenantA] = $this->createOperationalChild();
        [, $tenantB] = $this->createOperationalChild();

        $client = Client::factory()->for($tenantA)->create();
        $plan = FinancePlan::factory()->forTenant($tenantA)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();
        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'number' => 1,
            'status' => BillingStatus::PENDING,
        ]);

        Sanctum::actingAs($this->createAdmin($tenantB));

        $this->deleteJson("/api/finance/billings/{$billing->uuid}")
            ->assertNotFound();

        $this->assertNotSoftDeleted('finance_billings', ['id' => $billing->getKey()]);
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
