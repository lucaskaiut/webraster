<?php

namespace Tests\Feature\Crm;

use App\Modules\Crm\Models\Pipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CrmPipelineTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_admin_can_create_pipeline_with_default_stages_flow(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->postJson('/api/crm/pipelines', [
            'name' => 'Vendas',
            'is_default' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('crm_pipelines', [
            'tenant_id' => $tenant->getKey(),
            'name' => 'Vendas',
            'is_default' => true,
        ]);
    }

    public function test_can_create_stage_and_reorder(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $pipeline = Pipeline::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Funil',
            'active' => true,
            'is_default' => true,
        ]);

        $stageA = $this->postJson("/api/crm/pipelines/{$pipeline->uuid}/stages", [
            'name' => 'Novo',
            'is_initial' => true,
            'position' => 0,
        ])->assertCreated()->json('data.id');

        $stageB = $this->postJson("/api/crm/pipelines/{$pipeline->uuid}/stages", [
            'name' => 'Qualificação',
            'position' => 1,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/crm/pipelines/{$pipeline->uuid}/stages/reorder", [
            'stage_ids' => [$stageB, $stageA],
        ])->assertOk();
    }
}
