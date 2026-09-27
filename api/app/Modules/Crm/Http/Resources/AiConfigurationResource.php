<?php

namespace App\Modules\Crm\Http\Resources;

use App\Modules\Crm\Models\AiConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiConfiguration */
class AiConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'enabled' => $this->enabled,
            'api_endpoint' => $this->api_endpoint,
            'has_api_key' => filled($this->api_key),
            'system_prompt' => $this->system_prompt,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'settings' => $this->settings,
        ];
    }
}
