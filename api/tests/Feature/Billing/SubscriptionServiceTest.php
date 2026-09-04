<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.active' => ['mockPix', 'asaasCreditCard']]);
    }

    public function test_create_subscription_with_trial_sets_trialing_and_next_billing(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->create([
            'free_trial_days' => 7,
            'price' => '49.90',
        ]);

        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan, 'mockPix');

        $this->assertSame(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertSame('2026-07-08 12:00:00', $subscription->next_billing_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-08 12:00:00', $subscription->trial_ends_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event' => 'TRIAL_STARTED',
        ]);
    }

    public function test_create_subscription_without_trial_bills_immediately(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();

        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan, 'mockPix');

        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertSame('2026-07-01 12:00:00', $subscription->next_billing_at->format('Y-m-d H:i:s'));
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_confirm_payment_advances_next_billing_by_recurrence(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'recurrence_value' => 30,
        ]);

        $service = app(SubscriptionService::class);
        $subscription = $service->createForTenant($child, $plan, 'mockPix');

        Carbon::setTestNow('2026-01-01 11:00:00');
        $updated = $service->confirmPayment($subscription);

        $this->assertSame(SubscriptionStatus::ACTIVE, $updated->status);
        $this->assertSame('2026-01-01 11:00:00', $updated->last_billed_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-31 11:00:00', $updated->next_billing_at->format('Y-m-d H:i:s'));
    }

    public function test_root_without_children_can_manage_plans(): void
    {
        $root = $this->createTenantWithRoles();
        $admin = $this->createAdmin($root);

        Sanctum::actingAs($admin);
        TenantContext::set($root);

        $this->postJson('/api/billing/plans', [
            'name' => 'Plano Solo',
            'price' => 10,
            'recurrence_value' => 30,
            'recurrence_unit' => 'days',
        ])->assertCreated();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.is_umbrella', true);

        $permissions = $this->getJson('/api/auth/me')->json('data.permissions');
        $this->assertContains('plan.read', $permissions);
        $this->assertContains('plan.create', $permissions);
    }

    public function test_only_umbrella_can_create_plans_via_api(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $childAdmin = $this->createAdmin($child);

        Sanctum::actingAs($childAdmin);
        TenantContext::set($child);

        $this->postJson('/api/billing/plans', [
            'name' => 'Plano Filho',
            'price' => 10,
            'recurrence_value' => 30,
            'recurrence_unit' => 'days',
        ])->assertForbidden();

        $umbrellaAdmin = $this->createAdmin($umbrella);
        Sanctum::actingAs($umbrellaAdmin);
        TenantContext::set($umbrella);

        $this->postJson('/api/billing/plans', [
            'name' => 'Plano Básico',
            'price' => 49.90,
            'recurrence_value' => 30,
            'recurrence_unit' => 'days',
            'free_trial_days' => 7,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Plano Básico')
            ->assertJsonPath('data.price', '49.90');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.is_umbrella', true);

        $permissions = $this->getJson('/api/auth/me')->json('data.permissions');
        $this->assertContains('plan.read', $permissions);
    }

    public function test_register_with_plan_creates_subscription(): void
    {
        $umbrella = $this->createTenantWithRoles([
            'domain' => 'grupo.com.br',
            'email' => 'contato@grupo.com.br',
        ]);
        $this->createChildTenant($umbrella);

        $plan = Plan::factory()->forTenant($umbrella)->create([
            'free_trial_days' => 7,
            'active' => true,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'tenant' => [
                'name' => 'Empresa Nova',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@nova.com',
                'phone' => '41999999999',
                'domain' => 'nova.com.br',
            ],
            'user' => [
                'name' => 'Admin Nova',
                'email' => 'admin@nova.com',
                'phone' => '41999999999',
                'document' => '529.982.247-25',
                'password' => '12345678',
            ],
            'plan_id' => $plan->uuid,
        ])->assertCreated();

        $this->assertDatabaseHas('subscriptions', [
            'plan_id' => $plan->getKey(),
            'payment_gateway' => null,
            'status' => SubscriptionStatus::TRIALING->value,
        ]);

        $this->assertDatabaseMissing('invoices', [
            'status' => 'PENDING',
        ]);

        $this->assertFalse($response->json('data.requires_payment'));
        $this->assertTrue($response->json('data.is_trial'));
        $this->assertSame(7, $response->json('data.trial_days'));
        $this->assertSame('trialing', $response->json('data.billing_status'));
        $this->assertNull($response->json('data.invoice'));
        // Tenant criado no register é filho do umbrella (parent_id preenchido).
        $this->assertFalse((bool) $response->json('data.tenant.is_umbrella'));
    }

    public function test_register_with_paid_plan_creates_local_invoice(): void
    {
        $umbrella = $this->createTenantWithRoles([
            'domain' => 'grupo-pago.com.br',
            'email' => 'contato@grupo-pago.com.br',
        ]);
        $this->createChildTenant($umbrella);

        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'active' => true,
            'price' => '99.90',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'tenant' => [
                'name' => 'Empresa Paga',
                'document' => '04.252.011/0001-10',
                'email' => 'contato@paga.com',
                'phone' => '41999999999',
                'domain' => 'paga.com.br',
            ],
            'user' => [
                'name' => 'Admin Paga',
                'email' => 'admin@paga.com',
                'phone' => '41999999999',
                'document' => '529.982.247-25',
                'password' => '12345678',
            ],
            'plan_id' => $plan->uuid,
        ])->assertCreated();

        $this->assertDatabaseHas('subscriptions', [
            'plan_id' => $plan->getKey(),
            'status' => SubscriptionStatus::ACTIVE->value,
            'payment_gateway' => null,
        ]);

        $this->assertTrue($response->json('data.requires_payment'));
        $this->assertFalse($response->json('data.is_trial'));
        $this->assertSame(0, $response->json('data.trial_days'));
        $this->assertSame('pending_payment', $response->json('data.billing_status'));
        $this->assertTrue($response->json('data.invoice.awaiting_payment_method'));
        $this->assertNull($response->json('data.invoice.gateway'));

        $invoiceId = $response->json('data.invoice.id');
        $token = $response->json('data.token');

        $this->assertNotEmpty($invoiceId);

        $this->getJson("/api/billing/invoices/{$invoiceId}", [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $invoiceId)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.awaiting_payment_method', true);
    }

    public function test_complimentary_subscription_is_excluded_from_due_for_billing(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();

        $service = app(SubscriptionService::class);
        $service->grantComplimentary($child, $plan);

        $this->assertCount(0, $service->dueForBilling());

        $paidChild = $this->createChildTenant($umbrella, [
            'domain' => 'paga.com.br',
            'email' => 'contato@paga.com.br',
        ]);
        $paid = $service->createForTenant($paidChild, $plan);
        $paid->forceFill(['next_billing_at' => now()->subMinute()])->save();

        $due = $service->dueForBilling();
        $this->assertCount(1, $due);
        $this->assertSame($paid->getKey(), $due->first()->getKey());
    }

    public function test_expire_complimentary_revokes_and_schedules_billing(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();

        $service = app(SubscriptionService::class);
        $subscription = $service->grantComplimentary(
            $child,
            $plan,
            Carbon::parse('2026-09-01')->endOfDay(),
        );

        $this->assertTrue($subscription->is_complimentary);
        $this->assertFalse($subscription->fresh()->isComplimentaryActive());

        \Illuminate\Support\Facades\Artisan::call('billing:suspend-expired-subscriptions');

        $subscription->refresh();

        $this->assertFalse($subscription->is_complimentary);
        $this->assertNotNull($subscription->next_billing_at);
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event' => 'COMPLIMENTARY_EXPIRED',
        ]);
    }

    public function test_should_not_suspend_active_complimentary_subscription(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();

        $subscription = app(SubscriptionService::class)->grantComplimentary($child, $plan);
        $subscription->forceFill(['started_at' => now()->subDays(60)])->save();

        $this->assertFalse(app(\App\Modules\Billing\Services\BillingService::class)->shouldSuspend($subscription->fresh()));
    }
}
