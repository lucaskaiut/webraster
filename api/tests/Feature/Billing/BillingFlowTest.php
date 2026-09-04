<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Gateways\MockPixGateway;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Support\SubscriptionSuspensionRules;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.active' => ['mockPix', 'asaasCreditCard'],
            'billing.recurring_charge_max_attempts' => 3,
            'billing.recurring_charge_retry_delay_ms' => 0,
            'billing.recurring_remote_ip' => '203.0.113.10',
        ]);
        app(MockPixGateway::class)->payInstantly(false);
    }

    public function test_generate_invoice_creates_local_charge_without_gateway(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa A',
            'email' => 'a@empresa.com',
            'document' => '11222333000181',
        ]);

        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '149.90',
        ]);

        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $subscription->forceFill(['next_billing_at' => now()->subMinute()])->save();

        $invoice = app(BillingService::class)->generateInvoice($subscription->fresh(['plan', 'tenant']));

        $this->assertSame('149.90', (string) $invoice->amount);
        $this->assertSame(InvoiceStatus::PENDING, $invoice->status);
        $this->assertNull($invoice->gateway);
        $this->assertNull($invoice->external_id);
        $this->assertNull($invoice->pix_code);
        $this->assertTrue($invoice->awaitsPaymentMethod());
    }

    public function test_initiate_payment_creates_gateway_charge(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create(['price' => '49.90']);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);
        $invoice = $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant']));

        $paid = $billing->initiatePayment($invoice, 'mockPix');

        $this->assertSame('mockPix', $paid->gateway);
        $this->assertNotEmpty($paid->external_id);
        $this->assertNotEmpty($paid->pix_code);
        $this->assertFalse($paid->awaitsPaymentMethod());
        $this->assertSame('mockPix', $subscription->fresh()->payment_gateway);
    }

    public function test_recurring_credit_card_payment_persists_generic_token_on_subscription(): void
    {
        config([
            'asaas.api_key' => '$aact_hmlg_test_key',
            'asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
            'asaas.user_agent' => 'NoxConnectTest/1.0',
            'asaas.timeout' => 30,
            'asaas.credit_card_timeout' => 60,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api-sandbox.asaas.com/v3/customers*' => \Illuminate\Support\Facades\Http::response([
                'data' => [],
                'totalCount' => 0,
            ]),
            'api-sandbox.asaas.com/v3/customers' => \Illuminate\Support\Facades\Http::response([
                'id' => 'cus_recurring',
                'name' => 'Empresa A',
                'email' => 'a@empresa.com',
                'cpfCnpj' => '11222333000181',
            ]),
            'api-sandbox.asaas.com/v3/payments' => \Illuminate\Support\Facades\Http::response([
                'id' => 'pay_recurring',
                'status' => 'CONFIRMED',
                'value' => 49.9,
                'billingType' => 'CREDIT_CARD',
                'dueDate' => now()->toDateString(),
                'creditCard' => [
                    'creditCardNumber' => '1111',
                    'creditCardBrand' => 'VISA',
                    'creditCardToken' => 'tok_saved_generic',
                ],
            ]),
        ]);

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa A',
            'email' => 'a@empresa.com',
            'document' => '11222333000181',
            'phone' => '47999999999',
        ]);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create(['price' => '49.90']);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);
        $invoice = $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant']));

        $paid = $billing->initiatePayment($invoice, 'asaasCreditCard', [
            'recurring' => true,
            'credit_card' => [
                'holder_name' => 'Maria Silva',
                'number' => '4111111111111111',
                'expiration_month' => '12',
                'expiration_year' => '2030',
                'cvv' => '123',
                'postal_code' => '01310100',
                'address_number' => '100',
            ],
        ]);

        $subscription->refresh();

        $this->assertSame('asaasCreditCard', $paid->gateway);
        $this->assertTrue($subscription->recurring);
        $this->assertSame('tok_saved_generic', $subscription->credit_card_token);
        $this->assertArrayNotHasKey('credit_card_token', $paid->metadata ?? []);
    }

    public function test_instant_paid_initiate_payment_advances_billing_cycle(): void
    {
        Carbon::setTestNow('2026-03-01 09:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '49.90',
            'recurrence_value' => 30,
        ]);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);
        $invoice = $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant']));

        app(MockPixGateway::class)->payInstantly();

        $paid = $billing->initiatePayment($invoice, 'mockPix');

        $this->assertSame(InvoiceStatus::PAID, $paid->status);
        $this->assertNotNull($paid->paid_at);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertSame('2026-03-01 09:00:00', $subscription->last_billed_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-31 09:00:00', $subscription->next_billing_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event' => 'PAYMENT_CONFIRMED',
        ]);
    }

    public function test_reconcile_repairs_paid_invoice_without_billing_cycle(): void
    {
        Carbon::setTestNow('2026-04-10 12:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '49.90',
            'recurrence_value' => 30,
        ]);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);

        $invoice = $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant']));
        $invoice->forceFill([
            'gateway' => 'mockPix',
            'external_id' => 'pay_legacy',
            'status' => InvoiceStatus::PAID,
            'paid_at' => null,
        ])->save();

        $fixed = $billing->reconcileUnconfirmedPaidInvoices();

        $this->assertSame(1, $fixed);
        $this->assertNotNull($invoice->fresh()->paid_at);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertSame('2026-04-10 12:00:00', $subscription->last_billed_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-10 12:00:00', $subscription->next_billing_at->format('Y-m-d H:i:s'));
    }

    public function test_payment_confirmation_updates_subscription_and_invoice(): void
    {
        Carbon::setTestNow('2026-03-01 09:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '49.90',
            'recurrence_value' => 30,
        ]);

        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);
        $invoice = $billing->initiatePayment(
            $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant'])),
            'mockPix',
        );

        /** @var MockPixGateway $gateway */
        $gateway = app(MockPixGateway::class);
        $gateway->markAsPaid($invoice->external_id);

        $synced = $billing->syncInvoiceStatus($invoice->fresh());

        $this->assertSame(InvoiceStatus::PAID, $synced->status);
        $this->assertNotNull($synced->paid_at);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertSame('2026-03-31 09:00:00', $subscription->next_billing_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event' => 'PAYMENT_CONFIRMED',
        ]);
    }

    public function test_expired_payment_marks_subscription_past_due(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);

        $billing = app(BillingService::class);
        $invoice = $billing->initiatePayment(
            $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant'])),
            'mockPix',
        );

        app(MockPixGateway::class)->markAsExpired($invoice->external_id);
        $billing->syncInvoiceStatus($invoice->fresh());

        $this->assertSame(InvoiceStatus::EXPIRED, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->fresh()->status);
    }

    public function test_local_invoice_expires_and_marks_past_due(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);

        $invoice = $billing->createLocalInvoice(
            $subscription->fresh(['plan', 'tenant']),
            dueDate: now()->subDay()->toImmutable(),
            expiresAt: now()->subHour()->toImmutable(),
        );

        $billing->expireOverdueLocalInvoices();

        $this->assertSame(InvoiceStatus::EXPIRED, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->fresh()->status);
    }

    public function test_pay_endpoint_initiates_gateway_payment(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create(['price' => '79.90']);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $invoice = app(BillingService::class)->createLocalInvoice($subscription->fresh(['plan', 'tenant']));

        $admin = $this->createAdmin($child);
        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $this->postJson("/api/billing/invoices/{$invoice->uuid}/pay", [
            'payment_gateway' => 'mockPix',
        ])
            ->assertOk()
            ->assertJsonPath('data.gateway', 'mockPix')
            ->assertJsonPath('data.awaiting_payment_method', false);

        $this->assertNotEmpty($invoice->fresh()->pix_code);
    }

    public function test_generate_invoices_command_creates_due_charges(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create(['price' => '59.90']);

        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $subscription->forceFill([
            'status' => SubscriptionStatus::ACTIVE,
            'next_billing_at' => now()->subHour(),
        ])->save();

        Artisan::call('billing:generate-invoices');

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->getKey(),
            'amount' => '59.90',
            'status' => InvoiceStatus::PENDING->value,
            'gateway' => null,
        ]);
    }

    public function test_generate_invoice_charges_recurring_subscription_with_token(): void
    {
        config([
            'asaas.api_key' => '$aact_hmlg_test_key',
            'asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
            'asaas.user_agent' => 'NoxConnectTest/1.0',
            'asaas.timeout' => 30,
            'asaas.credit_card_timeout' => 60,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api-sandbox.asaas.com/v3/customers*' => \Illuminate\Support\Facades\Http::response([
                'data' => [[
                    'id' => 'cus_recurring',
                    'name' => 'Empresa A',
                    'email' => 'a@empresa.com',
                    'cpfCnpj' => '11222333000181',
                ]],
                'totalCount' => 1,
            ]),
            'api-sandbox.asaas.com/v3/payments' => \Illuminate\Support\Facades\Http::response([
                'id' => 'pay_auto',
                'status' => 'CONFIRMED',
                'value' => 59.9,
                'billingType' => 'CREDIT_CARD',
                'dueDate' => now()->toDateString(),
                'creditCard' => [
                    'creditCardToken' => 'tok_saved_generic',
                ],
            ]),
        ]);

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa A',
            'email' => 'a@empresa.com',
            'document' => '11222333000181',
            'phone' => '47999999999',
        ]);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '59.90',
            'recurrence_value' => 30,
        ]);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $subscription->forceFill([
            'status' => SubscriptionStatus::ACTIVE,
            'next_billing_at' => now()->subHour(),
            'payment_gateway' => 'asaasCreditCard',
            'recurring' => true,
            'credit_card_token' => 'tok_saved_generic',
        ])->save();

        $invoice = app(BillingService::class)->generateInvoice($subscription->fresh(['plan', 'tenant']));

        $this->assertSame('asaasCreditCard', $invoice->gateway);
        $this->assertSame('pay_auto', $invoice->external_id);
        $this->assertSame(InvoiceStatus::PAID, $invoice->status);
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->fresh()->status);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/payments')
                && ($body['creditCardToken'] ?? null) === 'tok_saved_generic';
        });
    }

    public function test_generate_invoice_falls_back_to_local_after_recurring_retries_fail(): void
    {
        config([
            'asaas.api_key' => '$aact_hmlg_test_key',
            'asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
            'asaas.user_agent' => 'NoxConnectTest/1.0',
            'asaas.timeout' => 30,
            'asaas.credit_card_timeout' => 60,
            'billing.recurring_charge_max_attempts' => 3,
        ]);

        $paymentCalls = 0;

        \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$paymentCalls) {
            if (str_contains($request->url(), '/customers')) {
                return \Illuminate\Support\Facades\Http::response([
                    'data' => [[
                        'id' => 'cus_fail',
                        'name' => 'Empresa A',
                        'email' => 'a@empresa.com',
                        'cpfCnpj' => '11222333000181',
                    ]],
                    'totalCount' => 1,
                ]);
            }

            if (str_contains($request->url(), '/payments')) {
                $paymentCalls++;

                return \Illuminate\Support\Facades\Http::response([
                    'errors' => [[
                        'code' => 'invalid_creditCard',
                        'description' => 'Transação não autorizada',
                    ]],
                ], 400);
            }

            return \Illuminate\Support\Facades\Http::response(['errors' => [['description' => 'unexpected']]], 500);
        });

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa A',
            'email' => 'a@empresa.com',
            'document' => '11222333000181',
            'phone' => '47999999999',
        ]);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create(['price' => '59.90']);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $subscription->forceFill([
            'status' => SubscriptionStatus::ACTIVE,
            'next_billing_at' => now()->subHour(),
            'payment_gateway' => 'asaasCreditCard',
            'recurring' => true,
            'credit_card_token' => 'tok_bad',
        ])->save();

        $invoice = app(BillingService::class)->generateInvoice($subscription->fresh(['plan', 'tenant']));

        $this->assertSame(3, $paymentCalls);
        $this->assertNull($invoice->gateway);
        $this->assertNull($invoice->external_id);
        $this->assertTrue($invoice->awaitsPaymentMethod());
        $this->assertSame(InvoiceStatus::PENDING, $invoice->status);
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event' => 'PAYMENT_FAILED',
        ]);
    }

    public function test_suspend_command_blocks_after_max_expired_invoices(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->create();

        $subscription = Subscription::factory()
            ->forTenant($child)
            ->forPlan($plan)
            ->pastDue()
            ->create();

        Invoice::factory()
            ->count(SubscriptionSuspensionRules::MAX_EXPIRED_INVOICES)
            ->forSubscription($subscription)
            ->expired()
            ->create();

        Artisan::call('billing:suspend-expired-subscriptions');

        $this->assertSame(SubscriptionStatus::SUSPENDED, $subscription->fresh()->status);
    }

    public function test_middleware_blocks_child_without_active_subscription(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        TenantContext::set($child);

        $middleware = app(\App\Modules\Billing\Http\Middleware\EnsureActiveSubscription::class);
        $result = $middleware->handle(
            request(),
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(402, $result->getStatusCode());
        $this->assertStringContainsString('Assinatura', $result->getContent());
    }

    public function test_list_invoices_for_current_tenant(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '49.90',
        ]);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $invoice = app(BillingService::class)->createLocalInvoice($subscription->fresh(['plan', 'tenant']));

        $admin = $this->createAdmin($child);
        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $this->getJson('/api/billing/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->uuid)
            ->assertJsonPath('data.0.amount', '49.90')
            ->assertJsonPath('data.0.awaiting_payment_method', true);
    }
}
