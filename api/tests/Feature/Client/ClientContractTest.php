<?php

namespace Tests\Feature\Client;

use App\Modules\Client\Models\Client;
use App\Modules\Contract\Models\Contract;
use App\Modules\Service\Models\Service;
use App\Modules\Shared\Support\Document;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertJsonPath('data.valid_until', '2027-01-01')
            ->assertJsonPath('data.signature_status', 'pending')
            ->assertJsonPath('data.signature_status_label', 'Pendente')
            ->assertJsonPath('data.signed_at', null);

        $response = $this->getJson("/api/clients/{$client->uuid}/contract")->assertOk();

        $this->assertStringContainsString('Transportadora Silva', $response->json('data.body'));
        $this->assertStringContainsString('Rastreamento', $response->json('data.body'));
        $this->assertStringContainsString('ABC1D23', $response->json('data.body'));
    }

    public function test_update_signature_status(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract/signature", [
            'signature_status' => 'signed',
        ])->assertNotFound();

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        $this->putJson("/api/clients/{$client->uuid}/contract/signature", [
            'signature_status' => 'signed',
        ])
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'signed')
            ->assertJsonPath('data.signature_status_label', 'Assinado');

        $this->assertNotNull(
            $this->getJson("/api/clients/{$client->uuid}/contract")->json('data.signed_at'),
        );

        $this->putJson("/api/clients/{$client->uuid}/contract/signature", [
            'signature_status' => 'pending',
        ])
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'pending')
            ->assertJsonPath('data.signed_at', null);

        $this->putJson("/api/clients/{$client->uuid}/contract/signature", [
            'signature_status' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors(['signature_status']);
    }

    public function test_reassigning_same_contract_keeps_signature_but_new_contract_resets_it(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();
        $another = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        $this->putJson("/api/clients/{$client->uuid}/contract/signature", [
            'signature_status' => 'signed',
        ])->assertOk();

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-06-01',
        ])->assertOk()->assertJsonPath('data.signature_status', 'signed');

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $another->uuid,
            'valid_until' => '2027-06-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'pending')
            ->assertJsonPath('data.signed_at', null);
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

        $this->putJson("/api/clients/{$client->uuid}/contract/signature", [
            'signature_status' => 'signed',
        ])->assertForbidden();
    }

    public function test_client_user_signs_own_contract_with_image(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        Storage::fake('public');

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $response = $this->post(
            "/api/clients/{$client->uuid}/contract/signature",
            [
                'image' => UploadedFile::fake()->image('assinatura.png'),
                'contract_id' => $contract->uuid,
                'signer_name' => 'Maria da Silva',
                'signer_cpf' => '529.982.247-25',
                'signer_birth_date' => '1990-05-20',
            ],
            ['Accept' => 'application/json'],
        );

        $response->assertOk()
            ->assertJsonPath('data.signature_status', 'signed')
            ->assertJsonPath('data.signature_status_label', 'Assinado')
            ->assertJsonPath('data.signer_name', 'Maria da Silva')
            ->assertJsonPath('data.signer_cpf', '52998224725')
            ->assertJsonPath('data.signer_birth_date', '1990-05-20');

        $this->assertNotNull($response->json('data.signed_at'));
        $this->assertNotNull($response->json('data.signature_url'));

        Storage::disk('public')->assertExists($response->json('data.signature_path'));

        $this->assertDatabaseHas('client_contracts', [
            'client_id' => $client->getKey(),
            'signer_name' => 'Maria da Silva',
            'signer_cpf' => '52998224725',
        ]);
    }

    public function test_sign_validates_signer_data(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $this->post(
            "/api/clients/{$client->uuid}/contract/signature",
            [
                'image' => UploadedFile::fake()->image('assinatura.png'),
                'contract_id' => $contract->uuid,
                'signer_cpf' => '111.111.111-11',
                'signer_birth_date' => now()->addDay()->toDateString(),
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors([
            'signer_name',
            'signer_cpf',
            'signer_birth_date',
        ]);
    }

    public function test_sign_requires_contract_assigned_to_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $this->post(
            "/api/clients/{$client->uuid}/contract/signature",
            [
                'image' => UploadedFile::fake()->image('assinatura.png'),
                'contract_id' => $contract->uuid,
                'signer_name' => 'Maria da Silva',
                'signer_cpf' => '529.982.247-25',
                'signer_birth_date' => '1990-05-20',
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors(['contract_id']);
    }

    public function test_sign_rejects_contract_that_is_not_the_current_one(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();
        $another = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $this->post(
            "/api/clients/{$client->uuid}/contract/signature",
            [
                'image' => UploadedFile::fake()->image('assinatura.png'),
                'contract_id' => $another->uuid,
                'signer_name' => 'Maria da Silva',
                'signer_cpf' => '529.982.247-25',
                'signer_birth_date' => '1990-05-20',
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors(['contract_id']);
    }

    public function test_client_user_cannot_sign_another_clients_contract(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $otherClient = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$otherClient->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $this->post(
            "/api/clients/{$otherClient->uuid}/contract/signature",
            [
                'image' => UploadedFile::fake()->image('assinatura.png'),
                'contract_id' => $contract->uuid,
                'signer_name' => 'Maria da Silva',
                'signer_cpf' => '529.982.247-25',
                'signer_birth_date' => '1990-05-20',
            ],
            ['Accept' => 'application/json'],
        )->assertNotFound();
    }

    public function test_sign_validates_image(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $this->post(
            "/api/clients/{$client->uuid}/contract/signature",
            [
                'image' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
                'contract_id' => $contract->uuid,
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors(['image']);
    }

    public function test_member_without_sign_permission_cannot_sign(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        Sanctum::actingAs($this->createMember($tenant));

        $this->post(
            "/api/clients/{$client->uuid}/contract/signature",
            [
                'image' => UploadedFile::fake()->image('assinatura.png'),
                'contract_id' => $contract->uuid,
                'signer_name' => 'Maria da Silva',
                'signer_cpf' => '529.982.247-25',
                'signer_birth_date' => '1990-05-20',
            ],
            ['Accept' => 'application/json'],
        )->assertForbidden();
    }

    public function test_reassigning_another_contract_clears_uploaded_signature(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();
        $another = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        Storage::fake('public');

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $this->post(
            "/api/clients/{$client->uuid}/contract/signature",
            [
                'image' => UploadedFile::fake()->image('assinatura.png'),
                'contract_id' => $contract->uuid,
                'signer_name' => 'Maria da Silva',
                'signer_cpf' => '529.982.247-25',
                'signer_birth_date' => '1990-05-20',
            ],
            ['Accept' => 'application/json'],
        )->assertOk()->assertJsonPath('data.signature_status', 'signed');

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $another->uuid,
            'valid_until' => '2027-06-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'pending')
            ->assertJsonPath('data.signed_at', null)
            ->assertJsonPath('data.signature_path', null)
            ->assertJsonPath('data.signature_url', null)
            ->assertJsonPath('data.signer_name', null)
            ->assertJsonPath('data.signer_cpf', null)
            ->assertJsonPath('data.signer_birth_date', null);
    }

    public function test_client_user_reads_own_contract_through_portal(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $contract = Contract::factory()->forTenant($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/clients/{$client->uuid}/contract", [
            'contract_id' => $contract->uuid,
            'valid_until' => '2027-01-01',
        ])->assertOk();

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $client->getKey()]));

        $this->getJson('/api/contract/portal')
            ->assertOk()
            ->assertJsonPath('data.contract_id', $contract->uuid)
            ->assertJsonPath('data.signature_status', 'pending');
    }

    public function test_portal_contract_endpoint_rejects_staff_user(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/contract/portal')->assertForbidden();
    }
}
