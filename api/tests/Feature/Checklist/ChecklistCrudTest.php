<?php

namespace Tests\Feature\Checklist;

use App\Modules\Checklist\Models\Checklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChecklistCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_creates_initial_and_final_checklists_with_all_question_types(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $initialId = $this->postJson('/api/checklists', [
            'name' => 'Checklist de chegada',
            'description' => 'Conferência feita ao chegar no cliente.',
            'type' => 'initial',
            'is_active' => true,
            'questions' => [
                [
                    'prompt' => 'Descreva o estado do veículo',
                    'answer_type' => 'text',
                    'photo_rule' => 'required',
                ],
                [
                    'prompt' => 'O equipamento estava instalado?',
                    'answer_type' => 'single_choice',
                    'photo_rule' => 'optional',
                    'options' => [
                        ['label' => 'Sim'],
                        ['label' => 'Não'],
                    ],
                ],
                [
                    'prompt' => 'Quais itens foram conferidos?',
                    'answer_type' => 'multiple_choice',
                    'photo_rule' => 'none',
                    'options' => [
                        ['label' => 'IMEI'],
                        ['label' => 'Alimentação'],
                        ['label' => 'Antena'],
                    ],
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'initial')
            ->assertJsonPath('data.questions.0.answer_type', 'text')
            ->assertJsonCount(2, 'data.questions.1.options')
            ->assertJsonPath('data.questions.2.options.2.label', 'Antena')
            ->json('data.id');

        $finalId = $this->postJson('/api/checklists', [
            'name' => 'Checklist de saída',
            'type' => 'final',
            'questions' => [[
                'prompt' => 'Serviço concluído?',
                'answer_type' => 'single_choice',
                'photo_rule' => 'required',
                'options' => [['label' => 'Sim'], ['label' => 'Não']],
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'final')
            ->json('data.id');

        $this->assertDatabaseHas('checklists', [
            'uuid' => $initialId,
            'tenant_id' => $tenant->getKey(),
            'type' => 'initial',
        ]);

        $this->assertDatabaseHas('checklist_questions', [
            'checklist_id' => Checklist::query()->where('uuid', $initialId)->value('id'),
            'answer_type' => 'multiple_choice',
            'photo_rule' => 'none',
        ]);

        $this->getJson('/api/checklists?type=initial')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $initialId)
            ->assertJsonPath('data.0.questions_count', 3);

        $this->getJson('/api/checklists?type=final')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $finalId);
    }

    public function test_updates_questions_and_reorders_by_array_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $id = $this->postJson('/api/checklists', [
            'name' => 'Checklist',
            'type' => 'initial',
            'questions' => [
                ['prompt' => 'Primeira', 'answer_type' => 'text', 'photo_rule' => 'none'],
                ['prompt' => 'Segunda', 'answer_type' => 'text', 'photo_rule' => 'optional'],
            ],
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/checklists/{$id}", [
            'name' => 'Checklist atualizado',
            'questions' => [
                ['prompt' => 'Segunda agora primeiro', 'answer_type' => 'text', 'photo_rule' => 'optional'],
                [
                    'prompt' => 'Nova pergunta de seleção',
                    'answer_type' => 'single_choice',
                    'photo_rule' => 'required',
                    'options' => [['label' => 'A'], ['label' => 'B']],
                ],
                ['prompt' => 'Primeira por último', 'answer_type' => 'text', 'photo_rule' => 'none'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Checklist atualizado')
            ->assertJsonCount(3, 'data.questions')
            ->assertJsonPath('data.questions.0.prompt', 'Segunda agora primeiro')
            ->assertJsonPath('data.questions.0.position', 0)
            ->assertJsonPath('data.questions.1.prompt', 'Nova pergunta de seleção')
            ->assertJsonPath('data.questions.2.position', 2);

        $checklist = Checklist::query()->where('uuid', $id)->firstOrFail();
        $this->assertSame(3, $checklist->questions()->count());
        $this->assertSame(2, $checklist->questions()->where('position', 1)->first()->options()->count());
    }

    public function test_validates_required_fields_and_options(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/checklists', [
            'name' => '',
            'type' => 'invalid',
            'questions' => [[
                'prompt' => '',
                'answer_type' => 'invalid',
                'photo_rule' => 'invalid',
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'name',
            'type',
            'questions.0.prompt',
            'questions.0.answer_type',
            'questions.0.photo_rule',
        ]);

        $this->postJson('/api/checklists', [
            'name' => 'Sem opções',
            'type' => 'initial',
            'questions' => [[
                'prompt' => 'Escolha',
                'answer_type' => 'single_choice',
                'photo_rule' => 'none',
                'options' => [],
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('questions.0.options');
    }

    public function test_requires_checklist_permissions_and_isolates_tenants(): void
    {
        [, $tenant] = $this->createOperationalChild();
        [, $otherTenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $id = $this->postJson('/api/checklists', [
            'name' => 'Checklist do tenant',
            'type' => 'initial',
            'questions' => [],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->createClient($tenant));

        $this->getJson('/api/checklists')->assertForbidden();
        $this->postJson('/api/checklists', [
            'name' => 'Sem permissão',
            'type' => 'initial',
        ])->assertForbidden();

        Sanctum::actingAs($this->createAdmin($otherTenant));

        $this->getJson("/api/checklists/{$id}")->assertNotFound();
        $this->getJson('/api/checklists')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_soft_deletes_checklist(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $id = $this->postJson('/api/checklists', [
            'name' => 'Checklist removível',
            'type' => 'initial',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/checklists/{$id}")->assertOk();

        $this->assertSoftDeleted('checklists', ['uuid' => $id]);
    }
}
