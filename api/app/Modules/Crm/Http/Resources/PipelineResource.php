<?php

namespace App\Modules\Crm\Http\Resources;

use App\Modules\Crm\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pipeline */
class PipelineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'is_default' => $this->is_default,
            'stages' => PipelineStageResource::collection($this->whenLoaded('stages')),
        ];
    }
}
