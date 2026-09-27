<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Pipeline;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PipelineService
{
    public function listActive(): Collection
    {
        return Pipeline::query()
            ->where('active', true)
            ->with(['stages' => fn ($q) => $q->where('active', true)->orderBy('position')])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Pipeline
    {
        return DB::transaction(function () use ($data): Pipeline {
            if (! empty($data['is_default'])) {
                Pipeline::query()->where('tenant_id', TenantContext::tenantId())->update(['is_default' => false]);
            }

            return Pipeline::query()->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createStage(Pipeline $pipeline, array $data): PipelineStage
    {
        if (! empty($data['is_initial'])) {
            $this->assertSingleInitial($pipeline, null);
        }

        $position = (int) ($data['position'] ?? PipelineStage::query()->where('pipeline_id', $pipeline->getKey())->max('position') + 1);

        return PipelineStage::query()->create([
            ...$data,
            'tenant_id' => $pipeline->tenant_id,
            'pipeline_id' => $pipeline->getKey(),
            'position' => $position,
        ]);
    }

    /**
     * @param  list<string>  $stageUuids
     */
    public function reorderStages(Pipeline $pipeline, array $stageUuids): void
    {
        DB::transaction(function () use ($pipeline, $stageUuids): void {
            foreach ($stageUuids as $index => $uuid) {
                PipelineStage::query()
                    ->where('pipeline_id', $pipeline->getKey())
                    ->where('uuid', $uuid)
                    ->update(['position' => $index]);
            }
        });
    }

    private function assertSingleInitial(Pipeline $pipeline, ?int $exceptStageId): void
    {
        $exists = PipelineStage::query()
            ->where('pipeline_id', $pipeline->getKey())
            ->where('is_initial', true)
            ->when($exceptStageId, fn ($q) => $q->where('id', '!=', $exceptStageId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'is_initial' => ['Já existe uma etapa inicial neste funil.'],
            ]);
        }
    }
}
