<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Crm\Http\Requests\StorePipelineRequest;
use App\Modules\Crm\Http\Requests\UpdatePipelineRequest;
use App\Modules\Crm\Http\Resources\PipelineResource;
use App\Modules\Crm\Models\Pipeline;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class PipelineController extends ApiController
{
    public function __construct(private readonly PipelineService $service) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Pipeline::class);

        return $this->success(PipelineResource::collection($this->service->listActive()));
    }

    public function store(StorePipelineRequest $request): JsonResponse
    {
        $this->authorize('create', Pipeline::class);

        $pipeline = $this->service->create($request->validated());

        return $this->created(PipelineResource::make($pipeline->load('stages')));
    }

    public function update(UpdatePipelineRequest $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        $pipeline->fill($request->validated())->save();

        return $this->success(PipelineResource::make($pipeline->fresh('stages')));
    }
}
