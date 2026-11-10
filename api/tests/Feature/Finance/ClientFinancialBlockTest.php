<?php

namespace Tests\Feature\Finance;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceCollectionSetting;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientFinancialBlockTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC'));
    }

    public function test_blocked_client_only_reaches_finance_portal(): void
    {
        [$tenant, $client, $user] = $this->clientUser();
        $this->configure($tenant, ['block_access_after_days' => 5]);
        $billing = $this->billing($client, '2026-11-05', BillingStatus::OVERDUE);

        Sanctum::actingAs($user);

        $this->getJson('/api/vehicles')
            ->assertForbidden()
            ->assertJsonStructure(['errors' => ['client_financial_block']]);

        $this->getJson('/api/finance/portal/billings')->assertOk();
        $this->getJson('/api/notifications')->assertOk();

        $this->getJson('/api/finance/portal/access')
            ->assertOk()
            ->assertJsonPath('data.blocked', true)
            ->assertJsonPath('data.overdue_count', 1)
            ->assertJsonPath('data.billing_id', $billing->uuid);

        $permissions = $this->getJson('/api/auth/me')->assertOk()->json('data.permissions');
        $this->assertContains('finance-portal.view', $permissions);
        $this->assertNotContains('vehicle.read', $permissions);
        $this->assertNotContains('tracking.read', $permissions);
    }

    public function test_payment_releases_access(): void
    {
        [$tenant, $client, $user] = $this->clientUser();
        $this->configure($tenant);
        $billing = $this->billing($client, '2026-11-01', BillingStatus::OVERDUE);

        Sanctum::actingAs($user);
        $this->getJson('/api/vehicles')->assertForbidden();

        $billing->forceFill(['status' => BillingStatus::PAID, 'paid_at' => now()])->save();

        $this->getJson('/api/vehicles')->assertOk();
        $this->getJson('/api/finance/portal/access')->assertJsonPath('data.blocked', false);
        $this->assertContains('vehicle.read', $this->getJson('/api/auth/me')->json('data.permissions'));
    }

    public function test_not_blocked_before_grace_period(): void
    {
        [$tenant, $client, $user] = $this->clientUser();
        $this->configure($tenant, ['block_access_after_days' => 5]);
        $this->billing($client, '2026-11-06', BillingStatus::OVERDUE);

        Sanctum::actingAs($user);

        $this->getJson('/api/vehicles')->assertOk();
    }

    public function test_not_blocked_when_option_is_disabled(): void
    {
        [$tenant, $client, $user] = $this->clientUser();
        $this->configure($tenant, ['block_access_enabled' => false]);
        $this->billing($client, '2026-10-01', BillingStatus::OVERDUE);

        Sanctum::actingAs($user);

        $this->getJson('/api/vehicles')->assertOk();
    }

    public function test_other_clients_and_staff_are_not_affected(): void
    {
        [$tenant, $client] = $this->clientUser();
        $this->configure($tenant);
        $this->billing($client, '2026-10-01', BillingStatus::OVERDUE);

        $other = Client::factory()->for($tenant)->create();
        $otherUser = $this->createClient($tenant);
        $otherUser->forceFill(['client_id' => $other->getKey()])->save();

        Sanctum::actingAs($otherUser);
        $this->getJson('/api/vehicles')->assertOk();

        Sanctum::actingAs($this->createAdmin($tenant));
        $this->getJson('/api/vehicles')->assertOk();
    }

    public function test_settings_expose_block_options(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/finance/collection-settings')
            ->assertOk()
            ->assertJsonPath('data.block_access_enabled', false)
            ->assertJsonPath('data.block_access_after_days', 5);

        $this->putJson('/api/finance/collection-settings', [
            'is_active' => false,
            'before_due_enabled' => true,
            'before_due_days' => 3,
            'due_date_enabled' => true,
            'overdue_enabled' => true,
            'overdue_interval_days' => 3,
            'block_access_enabled' => true,
            'block_access_after_days' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.block_access_enabled', true)
            ->assertJsonPath('data.block_access_after_days', 10);
    }

    /**
     * @return array{0: Tenant, 1: Client, 2: User}
     */
    private function clientUser(): array
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant);
        $user->forceFill(['client_id' => $client->getKey()])->save();

        return [$tenant, $client, $user];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configure(Tenant $tenant, array $overrides = []): void
    {
        $setting = new FinanceCollectionSetting(array_merge(['block_access_enabled' => true], $overrides));
        $setting->tenant_id = $tenant->getKey();
        $setting->save();
    }

    private function billing(Client $client, string $dueAt, BillingStatus $status): FinanceBilling
    {
        $plan = FinancePlan::factory()->forTenant($client->tenant)->create();
        $subscription = FinanceSubscription::factory()->forClient($client, $plan)->create();

        return FinanceBilling::factory()->forSubscription($subscription)->create([
            'status' => $status,
            'due_at' => $dueAt,
        ]);
    }
}
