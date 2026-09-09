<?php

namespace Tests\Feature\Finance;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Contracts\DeviceSuspensionProvider;
use App\Modules\Finance\Enums\ContractStatus;
use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Finance\Models\FinanceWebhookLog;
use App\Modules\Finance\Models\TenantAsaasConfig;
use App\Modules\Finance\Services\AsaasWebhookProcessor;
use App\Modules\Finance\Services\DelinquencyService;
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

    public function test_creates_plan_and_contract_with_subscription(): void
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

        $this->postJson('/api/finance/contracts', [
            'client_id' => $client->uuid,
            'plan_id' => $plan['id'],
            'starts_at' => now()->toDateString(),
            'due_day' => 10,
            'amount_cents' => 4990,
            'periodicity' => 'monthly',
            'device_quantity' => 2,
            'auto_renew' => true,
            'block_on_overdue' => true,
            'block_after_days' => 5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'CTR-000001')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.subscription.status', 'active');
    }

    public function test_generates_receivable_for_contract(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $contract = FinanceContract::factory()->forClient($client)->withPlan($plan)->create(['number' => 1]);
        \App\Modules\Finance\Models\FinanceSubscription::factory()->forContract($contract)->create([
            'status' => 'active',
            'next_billing_at' => now()->toDateString(),
        ]);

        $this->postJson('/api/finance/receivables', [
            'contract_id' => $contract->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'REC-000001')
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

    public function test_webhook_marks_receivable_received_idempotently(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $contract = FinanceContract::factory()->forClient($client)->withPlan($plan)->create(['number' => 1]);
        $receivable = FinanceReceivable::factory()->forContract($contract)->create([
            'number' => 1,
            'status' => ReceivableStatus::AWAITING_PAYMENT,
            'gateway_payment_id' => 'pay_123',
        ]);

        $this->createAsaasConfig($tenant, 'secret-token');

        $payload = [
            'id' => 'evt_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_123',
                'value' => 49.90,
                'status' => 'RECEIVED',
            ],
        ];

        $this->postJson("/api/webhooks/asaas/{$tenant->uuid}", $payload, [
            'asaas-access-token' => 'secret-token',
        ])->assertOk();

        $this->assertSame(ReceivableStatus::RECEIVED, $receivable->fresh()->status);

        $this->postJson("/api/webhooks/asaas/{$tenant->uuid}", $payload, [
            'asaas-access-token' => 'secret-token',
        ])->assertOk();

        $this->assertSame(1, FinanceWebhookLog::query()->where('event_id', 'evt_1')->count());
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
        $contract = FinanceContract::factory()->forClient($client)->withPlan($plan)->create([
            'number' => 1,
            'block_on_overdue' => true,
            'block_after_days' => 5,
            'status' => ContractStatus::ACTIVE,
        ]);

        $receivable = FinanceReceivable::factory()->forContract($contract)->create([
            'number' => 1,
            'status' => ReceivableStatus::OVERDUE,
            'due_at' => now()->subDays(6)->toDateString(),
        ]);

        $fakeProvider = new class implements DeviceSuspensionProvider
        {
            public array $suspended = [];

            public array $unsuspended = [];

            public function suspend(\App\Modules\Equipment\Models\Equipment $equipment): void
            {
                $equipment->forceFill(['billing_suspended_at' => now()])->save();
                $this->suspended[] = $equipment->getKey();
            }

            public function unsuspend(\App\Modules\Equipment\Models\Equipment $equipment): void
            {
                $equipment->forceFill(['billing_suspended_at' => null])->save();
                $this->unsuspended[] = $equipment->getKey();
            }
        };

        $this->app->instance(DeviceSuspensionProvider::class, $fakeProvider);

        app(DelinquencyService::class)->process();

        $this->assertNotNull($equipment->fresh()->billing_suspended_at);

        Sanctum::actingAs($this->createAdmin($tenant));
        $this->postJson("/api/finance/receivables/{$receivable->uuid}/mark-received")
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $this->assertNull($equipment->fresh()->billing_suspended_at);
    }

    public function test_portal_lists_only_own_receivables(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $contractA = FinanceContract::factory()->forClient($clientA)->withPlan($plan)->create(['number' => 1]);
        $contractB = FinanceContract::factory()->forClient($clientB)->withPlan($plan)->create(['number' => 2]);
        FinanceReceivable::factory()->forContract($contractA)->create(['number' => 1]);
        FinanceReceivable::factory()->forContract($contractB)->create(['number' => 2]);

        $user = $this->createClient($tenant);
        $user->forceFill(['client_id' => $clientA->getKey()])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/finance/portal/receivables')
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

    public function test_charge_creates_asaas_payment(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create([
            'document' => '39053344705',
            'email' => 'cliente@example.com',
        ]);
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $this->createAsaasConfig($tenant, 'wh_token', 'asaas_key');

        $plan = FinancePlan::factory()->forTenant($tenant)->create();
        $contract = FinanceContract::factory()->forClient($client)->withPlan($plan)->create(['number' => 1]);
        $receivable = FinanceReceivable::factory()->forContract($contract)->create([
            'number' => 1,
            'status' => ReceivableStatus::PENDING,
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

        $this->postJson("/api/finance/receivables/{$receivable->uuid}/charge", [
            'payment_method' => 'boleto',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.gateway_payment_id', 'pay_abc');
    }

    private function createAsaasConfig(
        \App\Modules\Tenant\Models\Tenant $tenant,
        string $webhookToken = 'secret-token',
        string $apiKey = 'test_key',
    ): TenantAsaasConfig {
        $config = new TenantAsaasConfig;
        $config->forceFill([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'api_key' => $apiKey,
            'webhook_token' => $webhookToken,
            'is_active' => true,
        ]);
        $config->save();

        return $config;
    }
}
