<?php

namespace Tests\Feature\Finance;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Enums\CollectionReminderStage;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceCollectionSetting;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\CollectionReminderService;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FinanceCollectionReminderTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->today = CarbonImmutable::parse('2026-11-10', (string) config('scheduling.timezone'));
        $this->travelTo($this->today->setTime(9, 0));
    }

    public function test_settings_default_to_inactive_with_default_messages(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/finance/collection-settings')
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.before_due_days', 3)
            ->assertJsonPath('data.overdue_interval_days', 3)
            ->assertJsonPath('data.due_date_message', CollectionReminderStage::DUE_DATE->defaultMessage());
    }

    public function test_updates_settings(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/finance/collection-settings', $this->payload([
            'before_due_days' => 5,
            'overdue_interval_days' => 7,
            'overdue_max_reminders' => 4,
            'overdue_message' => 'Pague {codigo}: {link}',
        ]))
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.before_due_days', 5)
            ->assertJsonPath('data.overdue_max_reminders', 4)
            ->assertJsonPath('data.overdue_message', 'Pague {codigo}: {link}');

        $this->putJson('/api/finance/collection-settings', $this->payload(['before_due_days' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('before_due_days');

        $this->assertSame(1, FinanceCollectionSetting::query()->count());
    }

    public function test_requires_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createClient($tenant));

        $this->getJson('/api/finance/collection-settings')->assertForbidden();
        $this->putJson('/api/finance/collection-settings', $this->payload())->assertForbidden();
    }

    public function test_sends_reminders_before_on_and_after_due_date(): void
    {
        [$tenant, $client] = $this->tenantWithClient();
        $this->configure($tenant, ['before_due_days' => 3, 'overdue_interval_days' => 3]);

        $before = $this->billing($client, '2026-11-13');
        $due = $this->billing($client, '2026-11-10');
        $lateDay1 = $this->billing($client, '2026-11-09', BillingStatus::OVERDUE);
        $lateDay4 = $this->billing($client, '2026-11-06', BillingStatus::OVERDUE);
        $lateDay2 = $this->billing($client, '2026-11-08', BillingStatus::OVERDUE);
        $farAway = $this->billing($client, '2026-11-20');
        $this->billing($client, '2026-11-10', BillingStatus::PAID);

        $this->artisan('finance:send-collection-reminders')->assertSuccessful();

        $this->assertReminder($before, CollectionReminderStage::BEFORE_DUE);
        $this->assertReminder($due, CollectionReminderStage::DUE_DATE);
        $this->assertReminder($lateDay1, CollectionReminderStage::OVERDUE);
        $this->assertReminder($lateDay4, CollectionReminderStage::OVERDUE);
        $this->assertNoReminder($lateDay2);
        $this->assertNoReminder($farAway);
        $this->assertSame(4, DB::table('finance_billing_events')->where('action', 'like', 'collection_reminder_%')->count());

        // Rodar de novo no mesmo dia não repete avisos.
        $this->artisan('finance:send-collection-reminders')->assertSuccessful();
        $this->assertSame(4, DB::table('finance_billing_events')->where('action', 'like', 'collection_reminder_%')->count());
    }

    public function test_renders_configured_text_with_payment_link(): void
    {
        [$tenant, $client] = $this->tenantWithClient();
        $this->configure($tenant, ['due_date_message' => '{cliente}, {codigo} de {valor} vence {vencimento}: {link}']);
        $billing = $this->billing($client, '2026-11-10', BillingStatus::AWAITING_PAYMENT, [
            'number' => 42,
            'amount_cents' => 12990,
            'invoice_url' => 'https://www.asaas.com/i/abc123',
        ]);

        $this->artisan('finance:send-collection-reminders')->assertSuccessful();

        $notification = DB::table('user_notifications')
            ->where('type', CollectionReminderStage::DUE_DATE->notificationType())
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame(
            "{$client->name}, 42 de R$ 129,90 vence 10/11/2026: https://www.asaas.com/i/abc123",
            $notification->body,
        );
        $this->assertReminder($billing, CollectionReminderStage::DUE_DATE);
    }

    public function test_falls_back_to_panel_link_without_gateway_invoice(): void
    {
        [$tenant, $client] = $this->tenantWithClient();
        $setting = $this->configure($tenant);
        $billing = $this->billing($client, '2026-11-10');

        TenantContext::set($tenant);
        $url = app(CollectionReminderService::class)->paymentUrl($billing);

        $this->assertStringEndsWith("/finance/portal/billings/{$billing->uuid}", $url);
        $this->assertTrue($setting->is_active);
    }

    public function test_respects_overdue_limit_and_disabled_stages(): void
    {
        [$tenant, $client] = $this->tenantWithClient();
        $setting = $this->configure($tenant, [
            'before_due_enabled' => false,
            'overdue_interval_days' => 1,
            'overdue_max_reminders' => 2,
        ]);

        $before = $this->billing($client, '2026-11-13');
        $late = $this->billing($client, '2026-11-05', BillingStatus::OVERDUE);
        TenantContext::set($tenant);
        $late->events()->create(['action' => 'collection_reminder_overdue']);
        $late->events()->create(['action' => 'collection_reminder_overdue']);
        $service = app(CollectionReminderService::class);

        $this->assertNull($service->stageFor($before, $setting, $this->today));
        $this->assertNull($service->stageFor($late, $setting, $this->today));
    }

    public function test_inactive_settings_send_nothing(): void
    {
        [$tenant, $client] = $this->tenantWithClient();
        $this->configure($tenant, ['is_active' => false]);
        $billing = $this->billing($client, '2026-11-10');

        $this->artisan('finance:send-collection-reminders')->assertSuccessful();

        $this->assertNoReminder($billing);
    }

    public function test_active_rule_replaces_legacy_due_soon_notification(): void
    {
        [$tenant, $client] = $this->tenantWithClient();
        $this->configure($tenant);
        $this->billing($client, '2026-11-13');

        $this->artisan('finance:notify-due-soon')->assertSuccessful();

        $this->assertSame(0, DB::table('user_notifications')->where('type', 'finance_invoice_due_soon')->count());
    }

    /**
     * @return array{0: Tenant, 1: Client}
     */
    private function tenantWithClient(): array
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create(['name' => 'Maria']);
        $user = $this->createClient($tenant);
        $user->forceFill(['client_id' => $client->getKey()])->save();

        return [$tenant, $client];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configure(Tenant $tenant, array $overrides = []): FinanceCollectionSetting
    {
        $setting = new FinanceCollectionSetting(array_merge(['is_active' => true], $overrides));
        $setting->tenant_id = $tenant->getKey();
        $setting->save();

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function billing(
        Client $client,
        string $dueAt,
        BillingStatus $status = BillingStatus::PENDING,
        array $attributes = [],
    ): FinanceBilling {
        $plan = FinancePlan::factory()->forTenant($client->tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();

        return FinanceBilling::factory()->forSubscription($subscription)->create(array_merge([
            'status' => $status,
            'due_at' => $dueAt,
        ], $attributes));
    }

    private function assertReminder(FinanceBilling $billing, CollectionReminderStage $stage): void
    {
        $this->assertTrue(
            DB::table('finance_billing_events')
                ->where('billing_id', $billing->getKey())
                ->where('action', $stage->eventAction())
                ->exists(),
            "Esperava aviso {$stage->value} para a cobrança {$billing->number}.",
        );
    }

    private function assertNoReminder(FinanceBilling $billing): void
    {
        $this->assertFalse(
            DB::table('finance_billing_events')
                ->where('billing_id', $billing->getKey())
                ->where('action', 'like', 'collection_reminder_%')
                ->exists(),
            "Não esperava aviso para a cobrança {$billing->number}.",
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'is_active' => true,
            'before_due_enabled' => true,
            'before_due_days' => 3,
            'before_due_message' => null,
            'due_date_enabled' => true,
            'due_date_message' => null,
            'overdue_enabled' => true,
            'overdue_interval_days' => 3,
            'overdue_max_reminders' => null,
            'overdue_message' => null,
            'block_access_enabled' => false,
            'block_access_after_days' => 5,
        ], $overrides);
    }
}
