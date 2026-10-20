<?php

namespace Tests\Feature\Checklist;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Checklist\Models\Checklist;
use App\Modules\Checklist\Models\ServiceOrderChecklist;
use App\Modules\Client\Models\Client;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceOrderChecklistFlowTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'traccar.enabled' => false,
            'scheduling.geocoding.enabled' => false,
            'scheduling.routing.enabled' => false,
        ]);
    }

    public function test_initial_and_final_checklists_gate_the_service_order_flow(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $initialId = $this->createChecklist('Checklist inicial', 'initial', [
            [
                'prompt' => 'Estado do veículo',
                'answer_type' => 'text',
                'photo_rule' => 'required',
            ],
            [
                'prompt' => 'Equipamento instalado?',
                'answer_type' => 'single_choice',
                'photo_rule' => 'optional',
                'options' => [['label' => 'Sim'], ['label' => 'Não']],
            ],
            [
                'prompt' => 'Itens conferidos',
                'answer_type' => 'multiple_choice',
                'photo_rule' => 'none',
                'options' => [['label' => 'IMEI'], ['label' => 'Alimentação'], ['label' => 'Antena']],
            ],
        ]);

        $finalId = $this->createChecklist('Checklist final', 'final', [
            [
                'prompt' => 'Observações finais',
                'answer_type' => 'text',
                'photo_rule' => 'optional',
            ],
            [
                'prompt' => 'Serviço concluído?',
                'answer_type' => 'single_choice',
                'photo_rule' => 'required',
                'options' => [['label' => 'Sim'], ['label' => 'Não']],
            ],
        ]);

        $orderId = $this->createOrder($client, $technician);

        $this->assertDatabaseHas('service_order_checklists', [
            'service_order_id' => ServiceOrder::query()->where('uuid', $orderId)->value('id'),
            'checklist_id' => $this->checklistInternalId($initialId),
            'type' => 'initial',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('service_order_checklists', [
            'service_order_id' => ServiceOrder::query()->where('uuid', $orderId)->value('id'),
            'checklist_id' => $this->checklistInternalId($finalId),
            'type' => 'final',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($technician);

        $order = $this->technicianOrder($orderId);
        $initial = $this->checklistByType($order, 'initial');
        $final = $this->checklistByType($order, 'final');

        $this->patchJson("/api/technician/service-orders/{$orderId}/status", [
            'status' => 'in_progress',
        ])->assertUnprocessable()->assertJsonValidationErrors('checklists');

        $textQuestion = $initial['questions'][0];
        $singleQuestion = $initial['questions'][1];
        $multipleQuestion = $initial['questions'][2];

        // Pergunta sem permissão de foto rejeita imagem mesmo pela API.
        $this->post("/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/questions/{$multipleQuestion['id']}/photos", [
            'file' => UploadedFile::fake()->image('nao-permitida.jpg', 40, 40),
        ])->assertUnprocessable();

        $photoId = $this->post("/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/questions/{$textQuestion['id']}/photos", [
            'file' => UploadedFile::fake()->image('evidencia.jpg', 80, 80),
        ])->assertCreated()->json('data.id');

        $this->get("/api/technician/checklist-photos/{$photoId}")->assertOk();

        $initialAnswers = [
            ['question_id' => $textQuestion['id'], 'text_answer' => 'Veículo em bom estado'],
            [
                'question_id' => $singleQuestion['id'],
                'option_ids' => [$singleQuestion['options'][0]['id']],
            ],
            [
                'question_id' => $multipleQuestion['id'],
                'option_ids' => [
                    $multipleQuestion['options'][0]['id'],
                    $multipleQuestion['options'][1]['id'],
                ],
            ],
        ];

        $this->putJson(
            "/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/answers",
            ['answers' => $initialAnswers],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Reenvio idempotente: atualiza as mesmas respostas, sem duplicar.
        $this->putJson(
            "/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/answers",
            ['answers' => $initialAnswers],
        )->assertOk();

        $this->assertSame(3, ServiceOrderChecklist::query()
            ->where('uuid', $initial['id'])
            ->firstOrFail()
            ->questions()
            ->withCount('answer')
            ->get()
            ->sum('answer_count'));

        $this->patchJson("/api/technician/service-orders/{$orderId}/status", [
            'status' => 'in_progress',
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $finalText = $final['questions'][0];
        $finalChoice = $final['questions'][1];

        $this->putJson(
            "/api/technician/service-orders/{$orderId}/checklists/{$final['id']}/answers",
            ['answers' => [
                ['question_id' => $finalText['id'], 'text_answer' => 'Tudo certo'],
                [
                    'question_id' => $finalChoice['id'],
                    'option_ids' => [$finalChoice['options'][0]['id']],
                ],
            ]],
        )->assertUnprocessable()->assertJsonValidationErrors('answers.1.photos');

        $this->patchJson("/api/technician/service-orders/{$orderId}/status", [
            'status' => 'completed',
        ])->assertUnprocessable()->assertJsonValidationErrors('checklists');

        $this->post("/api/technician/service-orders/{$orderId}/checklists/{$final['id']}/questions/{$finalChoice['id']}/photos", [
            'file' => UploadedFile::fake()->image('final.jpg', 80, 80),
        ])->assertCreated();

        $this->putJson(
            "/api/technician/service-orders/{$orderId}/checklists/{$final['id']}/answers",
            ['answers' => [
                ['question_id' => $finalText['id'], 'text_answer' => 'Tudo certo'],
                [
                    'question_id' => $finalChoice['id'],
                    'option_ids' => [$finalChoice['options'][0]['id']],
                ],
            ]],
        )->assertOk()->assertJsonPath('data.status', 'completed');

        $this->patchJson("/api/technician/service-orders/{$orderId}/status", [
            'status' => 'completed',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseCount('service_order_checklist_answers', 5);
        $this->assertDatabaseCount('service_order_checklist_photos', 2);
        $this->assertDatabaseHas('service_order_checklists', [
            'uuid' => $final['id'],
            'status' => 'completed',
            'completed_by' => $technician->getKey(),
        ]);
    }

    public function test_rejects_invalid_options_and_missing_photo_without_completing(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $this->createChecklist('Checklist inicial', 'initial', [[
            'prompt' => 'Tipo de instalação',
            'answer_type' => 'single_choice',
            'photo_rule' => 'required',
            'options' => [['label' => 'Nova'], ['label' => 'Troca']],
        ]]);

        $orderId = $this->createOrder($client, $technician);

        Sanctum::actingAs($technician);

        $initial = $this->checklistByType($this->technicianOrder($orderId), 'initial');
        $question = $initial['questions'][0];

        $this->putJson(
            "/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/answers",
            ['answers' => [[
                'question_id' => $question['id'],
                'option_ids' => ['00000000-0000-0000-0000-000000000000'],
            ]]],
        )->assertUnprocessable();

        $this->assertDatabaseHas('service_order_checklists', [
            'uuid' => $initial['id'],
            'status' => 'pending',
        ]);
    }

    public function test_removing_a_required_photo_blocks_start_until_it_is_replaced(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $this->createChecklist('Checklist inicial', 'initial', [[
            'prompt' => 'Foto da instalação',
            'answer_type' => 'text',
            'photo_rule' => 'required',
        ]]);

        $orderId = $this->createOrder($client, $technician);

        Sanctum::actingAs($technician);

        $initial = $this->checklistByType($this->technicianOrder($orderId), 'initial');
        $question = $initial['questions'][0];

        $photoId = $this->post("/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/questions/{$question['id']}/photos", [
            'file' => UploadedFile::fake()->image('required.jpg', 40, 40),
        ])->assertCreated()->json('data.id');

        $this->putJson(
            "/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/answers",
            ['answers' => [['question_id' => $question['id'], 'text_answer' => 'Instalado']]],
        )->assertOk();

        $this->deleteJson("/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/questions/{$question['id']}/photos/{$photoId}")
            ->assertOk();

        $this->patchJson("/api/technician/service-orders/{$orderId}/status", [
            'status' => 'in_progress',
        ])->assertUnprocessable()->assertJsonValidationErrors('checklists');

        $this->post("/api/technician/service-orders/{$orderId}/checklists/{$initial['id']}/questions/{$question['id']}/photos", [
            'file' => UploadedFile::fake()->image('required-2.jpg', 40, 40),
        ])->assertCreated();

        $this->patchJson("/api/technician/service-orders/{$orderId}/status", [
            'status' => 'in_progress',
        ])->assertOk();
    }

    public function test_template_changes_do_not_alter_existing_order_snapshot(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $checklistId = $this->createChecklist('Checklist inicial', 'initial', [[
            'prompt' => 'Pergunta original',
            'answer_type' => 'single_choice',
            'photo_rule' => 'none',
            'options' => [['label' => 'Original A'], ['label' => 'Original B']],
        ]]);

        $orderId = $this->createOrder($client, $technician);

        $this->putJson("/api/checklists/{$checklistId}", [
            'name' => 'Checklist alterado',
            'questions' => [[
                'prompt' => 'Pergunta alterada',
                'answer_type' => 'text',
                'photo_rule' => 'none',
            ]],
        ])->assertOk();

        Sanctum::actingAs($technician);

        $order = $this->technicianOrder($orderId);
        $initial = $this->checklistByType($order, 'initial');

        $this->assertSame('Checklist inicial', $initial['name']);
        $this->assertSame('Pergunta original', $initial['questions'][0]['prompt']);
        $this->assertSame('single_choice', $initial['questions'][0]['answer_type']);
        $this->assertSame('Original A', $initial['questions'][0]['options'][0]['label']);
    }

    public function test_other_technicians_cannot_access_or_submit_checklists(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createTechnician($tenant);
        $otherTechnician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $this->createChecklist('Checklist inicial', 'initial', [[
            'prompt' => 'Pergunta',
            'answer_type' => 'text',
            'photo_rule' => 'none',
        ]]);

        $orderId = $this->createOrder($client, $technician);

        Sanctum::actingAs($otherTechnician);

        $this->getJson("/api/technician/service-orders/{$orderId}")->assertNotFound();

        $instance = ServiceOrderChecklist::query()
            ->where('service_order_id', ServiceOrder::query()->where('uuid', $orderId)->value('id'))
            ->firstOrFail();

        $question = $instance->questions()->firstOrFail();

        $this->putJson(
            "/api/technician/service-orders/{$orderId}/checklists/{$instance->uuid}/answers",
            ['answers' => [[
                'question_id' => $question->uuid,
                'text_answer' => 'Tentativa indevida',
            ]]],
        )->assertNotFound();
    }

    public function test_client_portal_does_not_receive_operational_checklists(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();
        $clientUser = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($admin);

        $this->createChecklist('Checklist inicial', 'initial', [[
            'prompt' => 'Pergunta operacional',
            'answer_type' => 'text',
            'photo_rule' => 'none',
        ]]);

        $orderId = $this->createOrder($client, $technician);

        Sanctum::actingAs($clientUser);

        $this->getJson("/api/service-orders/{$orderId}")
            ->assertOk()
            ->assertJsonMissingPath('data.checklists');
    }

    public function test_cannot_create_order_in_progress_when_initial_checklist_is_pending(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $this->createChecklist('Checklist inicial', 'initial', [[
            'prompt' => 'Pergunta',
            'answer_type' => 'text',
            'photo_rule' => 'none',
        ]]);

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'status' => 'in_progress',
        ])->assertUnprocessable()->assertJsonValidationErrors('checklists');
    }

    private function createTechnician(Tenant $tenant): User
    {
        $technician = User::factory()->for($tenant)->create([
            'type' => UserType::TECHNICIAN->value,
        ]);

        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        return $technician;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     */
    private function createChecklist(string $name, string $type, array $questions): string
    {
        return $this->postJson('/api/checklists', [
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'questions' => $questions,
        ])->assertCreated()->json('data.id');
    }

    private function createOrder(Client $client, User $technician): string
    {
        $service = Service::factory()->forTenant($client->tenant)->create([
            'commission_type' => 'fixed',
            'commission_value' => 100,
        ]);

        return $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'items' => [[
                'service_id' => $service->uuid,
                'quantity' => 1,
                'unit_amount_cents' => 1000,
            ]],
        ])->assertCreated()->json('data.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function technicianOrder(string $orderId): array
    {
        return $this->getJson("/api/technician/service-orders/{$orderId}")
            ->assertOk()
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function checklistByType(array $order, string $type): array
    {
        foreach ($order['checklists'] as $checklist) {
            if ($checklist['type'] === $type) {
                return $checklist;
            }
        }

        $this->fail("Checklist {$type} não encontrado na OS.");
    }

    private function checklistInternalId(string $uuid): int
    {
        return (int) Checklist::query()
            ->where('uuid', $uuid)
            ->value('id');
    }
}
