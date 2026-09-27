<?php

namespace App\Modules\Crm\Http\Resources;

use App\Modules\Crm\Models\AiStageConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiStageConfiguration */
class AiStageConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stage_id' => $this->stage?->uuid,
            'enabled' => $this->enabled,
            'objective' => $this->objective,
            'instructions' => $this->instructions,
            'success_criteria' => $this->success_criteria,
            'allowed_actions' => $this->allowed_actions,
            'restricted_actions' => $this->restricted_actions,
            'temperature' => $this->temperature,
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
        ];
    }
}
