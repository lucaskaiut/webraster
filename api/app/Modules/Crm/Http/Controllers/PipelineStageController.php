<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Crm\Http\Requests\ReorderPipelineStagesRequest;
use App\Modules\Crm\Http\Requests\StorePipelineStageRequest;
use App\Modules\Crm\Http\Requests\UpdatePipelineStageRequest;
use App\Modules\Crm\Http\Resources\PipelineResource;
use App\Modules\Crm\Http\Resources\PipelineStageResource;
use App\Modules\Crm\Models\Pipeline;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class PipelineStageController extends ApiController
{
    public function __construct(private readonly PipelineService $service) {}

    public function store(StorePipelineStageRequest $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        $stage = $this->service->createStage($pipeline, $request->validated());

        return $this->created(PipelineStageResource::make($stage));
    }

    public function update(UpdatePipelineStageRequest $request, Pipeline $pipeline, PipelineStage $pipelineStage): JsonResponse
    {
        $this->authorize('update', $pipeline);

        abort_unless((int) $pipelineStage->pipeline_id === (int) $pipeline->getKey(), 404);

        $pipelineStage->fill($request->validated())->save();

        return $this->success(PipelineStageResource::make($pipelineStage));
    }

    public function reorder(ReorderPipelineStagesRequest $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        $this->service->reorderStages($pipeline, $request->validated('stage_ids'));

        return $this->success(PipelineResource::make($pipeline->fresh('stages')));
    }
}
