<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Crm\Http\Requests\MoveLeadStageRequest;
use App\Modules\Crm\Http\Requests\UpdateLeadRequest;
use App\Modules\Crm\Http\Resources\LeadResource;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pipeline;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends ApiController
{
    public function __construct(private readonly LeadService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $pipelineId = null;
        if ($request->filled('pipeline_id')) {
            $pipelineId = Pipeline::query()->where('uuid', $request->string('pipeline_id'))->value('id');
        }

        $stageId = null;
        if ($request->filled('stage_id')) {
            $stageId = PipelineStage::query()->where('uuid', $request->string('stage_id'))->value('id');
        }

        $leads = $this->service->paginate(
            (int) $request->integer('per_page', 20),
            $pipelineId,
            $stageId,
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(LeadResource::collection($leads));
    }

    public function kanban(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $pipeline = Pipeline::query()->where('uuid', $request->string('pipeline_id'))->firstOrFail();

        $grouped = $this->service->kanban((int) $pipeline->getKey());

        $payload = [];

        foreach ($grouped as $stageUuid => $leads) {
            $payload[$stageUuid] = LeadResource::collection(collect($leads));
        }

        return $this->success($payload);
    }

    public function show(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        return $this->success(LeadResource::make($lead->load(['contact', 'stage', 'pipeline', 'owner'])));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $lead->fill($request->validated())->save();

        return $this->success(LeadResource::make($lead->fresh(['contact', 'stage', 'pipeline', 'owner'])));
    }

    public function moveStage(MoveLeadStageRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $stage = PipelineStage::query()->where('uuid', $request->validated('stage_id'))->firstOrFail();

        $lead = $this->service->moveToStage($lead, $stage);

        return $this->success(LeadResource::make($lead->load(['contact', 'stage', 'pipeline', 'owner'])));
    }
}
