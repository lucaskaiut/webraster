<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Crm\Http\Requests\UpdateAiConfigurationRequest;
use App\Modules\Crm\Http\Requests\UpdateAiStageConfigurationRequest;
use App\Modules\Crm\Http\Resources\AiConfigurationResource;
use App\Modules\Crm\Http\Resources\AiStageConfigurationResource;
use App\Modules\Crm\Models\AiConfiguration;
use App\Modules\Crm\Models\AiStageConfiguration;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Crm\Support\DefaultCrmAiPrompts;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;

class CrmAiConfigurationController extends ApiController
{
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', AiConfiguration::class);

        $config = AiConfiguration::query()->firstOrCreate(
            ['tenant_id' => TenantContext::tenantId()],
            [
                'enabled' => false,
                'system_prompt' => DefaultCrmAiPrompts::systemPrompt(),
            ],
        );

        return $this->success(AiConfigurationResource::make($config));
    }

    public function update(UpdateAiConfigurationRequest $request): JsonResponse
    {
        $config = AiConfiguration::query()->firstOrCreate(
            ['tenant_id' => TenantContext::tenantId()],
            [
                'enabled' => false,
                'system_prompt' => DefaultCrmAiPrompts::systemPrompt(),
            ],
        );

        $this->authorize('update', $config);

        $data = $request->validated();

        if (array_key_exists('api_key', $data) && blank($data['api_key'])) {
            unset($data['api_key']);
        }

        $config->fill($data)->save();

        return $this->success(AiConfigurationResource::make($config));
    }

    public function updateStage(UpdateAiStageConfigurationRequest $request, PipelineStage $stage): JsonResponse
    {
        $this->authorize('update', AiConfiguration::class);

        $config = AiStageConfiguration::query()->updateOrCreate(
            ['stage_id' => $stage->getKey()],
            [
                'tenant_id' => TenantContext::tenantId(),
                ...$request->validated(),
            ],
        );

        return $this->success(AiStageConfigurationResource::make($config));
    }
}
