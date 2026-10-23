<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceOrderLoanAcceptanceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'traccar.enabled' => false,
            'scheduling.geocoding.enabled' => false,
            'scheduling.routing.enabled' => false,
        ]);
    }

    public function test_tenant_requires_term_when_loan_acceptance_is_enabled(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);

        Sanctum::actingAs($admin);

        $this->putJson('/api/tenant', [
            'require_equipment_loan_acceptance' => true,
            'equipment_loan_term' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment_loan_term');

        $term = '<p><strong>Termo</strong> de comodato do equipamento de rastreamento.</p>';

        $this->putJson('/api/tenant', [
            'require_equipment_loan_acceptance' => true,
            'equipment_loan_term' => $term,
        ])
            ->assertOk()
            ->assertJsonPath('data.require_equipment_loan_acceptance', true)
            ->assertJsonPath('data.equipment_loan_term', $term);
    }

    public function test_blocks_completion_without_customer_signature(): void
    {
        [, $order, $technician] = $this->makeOrderContext();

        Sanctum::actingAs($technician);

        $this->patchJson("/api/technician/service-orders/{$order->uuid}/status", [
            'status' => ServiceOrderStatus::COMPLETED->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('loan_term_acceptance');

        $this->assertSame(
            ServiceOrderStatus::IN_PROGRESS,
            $order->refresh()->status,
        );
    }

    public function test_technician_records_signature_without_loan_term(): void
    {
        Storage::fake('local');
        [$tenant, $order, $technician] = $this->makeOrderContext();

        // Termo cadastrado, mas a empresa não exige o aceite: a mensagem não
        // deve ser exibida nem congelada na OS.
        $tenant->forceFill([
            'require_equipment_loan_acceptance' => false,
            'equipment_loan_term' => 'Termo de comodato do rastreador.',
        ])->save();

        Sanctum::actingAs($technician);

        $this->post(
            "/api/technician/service-orders/{$order->uuid}/loan-acceptance",
            ['image' => UploadedFile::fake()->image('assinatura.png', 400, 160)],
            ['Accept' => 'application/json'],
        )
            ->assertOk()
            ->assertJsonPath('data.loan_term_required', false)
            ->assertJsonPath('data.loan_term_text', null)
            ->assertJsonPath('data.loan_term_signature_available', true)
            ->assertJsonPath('data.loan_term_accepted_text', null)
            ->assertJsonPath('data.loan_term_collected_by.name', $technician->name);

        $path = $order->refresh()->loan_term_signature_path;

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->assertNotNull($order->loan_term_accepted_at);
        $this->assertSame($technician->getKey(), $order->loan_term_collected_by);
        $this->assertNull($order->loan_term_text);
        $this->assertNull($order->loan_term_text_hash);

        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $order->getKey(),
            'user_id' => $technician->getKey(),
            'action' => 'loan_term_accepted',
            'field' => 'loan_term_acceptance',
        ]);

        $this->patchJson("/api/technician/service-orders/{$order->uuid}/status", [
            'status' => ServiceOrderStatus::COMPLETED->value,
        ])->assertOk();

        $this->assertSame(ServiceOrderStatus::COMPLETED, $order->refresh()->status);

        $this->get("/api/technician/service-orders/{$order->uuid}/loan-acceptance/signature", [
            'Accept' => 'image/png',
        ])->assertOk();
    }

    public function test_technician_records_signature_and_freezes_loan_term_when_enabled(): void
    {
        Storage::fake('local');
        [$tenant, $order, $technician] = $this->makeOrderContext();

        $tenant->forceFill([
            'require_equipment_loan_acceptance' => true,
            'equipment_loan_term' => 'Termo de comodato do rastreador.',
        ])->save();

        Sanctum::actingAs($technician);

        $this->post(
            "/api/technician/service-orders/{$order->uuid}/loan-acceptance",
            ['image' => UploadedFile::fake()->image('assinatura.png', 400, 160)],
            ['Accept' => 'application/json'],
        )
            ->assertOk()
            ->assertJsonPath('data.loan_term_required', true)
            ->assertJsonPath('data.loan_term_accepted_text', 'Termo de comodato do rastreador.');

        $order->refresh();

        $this->assertSame('Termo de comodato do rastreador.', $order->loan_term_text);
        $this->assertSame(hash('sha256', 'Termo de comodato do rastreador.'), $order->loan_term_text_hash);
    }

    /**
     * @return array{0: Tenant, 1: ServiceOrder, 2: User}
     */
    private function makeOrderContext(): array
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        $order = ServiceOrder::factory()->forClient($client)->inProgress()->create([
            'technician_id' => $technician->getKey(),
        ]);

        return [$tenant, $order, $technician];
    }
}
