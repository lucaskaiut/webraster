<?php

namespace Tests\Feature\Client;

use App\Modules\Client\Models\Client;
use App\Modules\Contract\Models\Contract;
use App\Modules\Service\Models\Service;
use App\Modules\Shared\Support\Document;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientContractTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_show_returns_null_when_no_contract_assigned(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson("/api/clients/{$client->uuid}/contract")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_assign_contract_renders_body_with_real_data(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create([
            'name' => 'Transportadora Silva',
            'document' => $document = Document::fakeCnpj(),
            'email' => 'contato@silva.com',
            'phone' => '41999998888',
        ]);

        $contract = Contract::factory()->forTenant($tenant)->create([
            'body' => '<p>Contrato de {{NOME_CLIENTE}} ({{DOCUMENTO_CLIENTE}}).</p><p>{{TABELA_PEDIDO}}</p>',
        ]);

        $service = Service::factory()->forTenant($tenant)->create(['name' => 'Rastreamento', 'amount_cents' => 5000]);
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1D23']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/order", [
            'due_day' => 10,
            'periodicity' => 'monthly',
            'items' => [
                ['service_id' => $service->uuid, 'vehicle_ids' => [$vehicle->uuid]],
            ],
        ])->assertOk();

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.contract_id', $contract->uuid)
            ->assertJsonPath('data.valid_until', '2027-01-01');

        $response = $this->getJson("/api/clients/{$client->uuid}/contract")->assertOk();

        $this->assertStringContainsString('Transportadora Silva', $response->json('data.body'));
        $this->assertStringContainsString('Rastreamento', $response->json('data.body'));
        $this->assertStringContainsString('ABC1D23', $response->json('data.body'));
    }

    public function test_assign_validates_contract_and_future_validity(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => 'inexistente',
            'valid_until' => '2027-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['contract_id']);

        $contract = Contract::factory()->forTenant($tenant)->create();

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2020-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['valid_until']);
    }

    public function test_member_without_permission_cannot_assign_contract(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createClient($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => 'x',
            'valid_until' => '2027-01-01',
        ])->assertForbidden();
    }
}
