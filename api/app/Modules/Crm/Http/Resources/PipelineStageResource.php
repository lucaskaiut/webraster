<?php

namespace App\Modules\Crm\Http\Resources;

use App\Modules\Crm\Models\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineStage */
class PipelineStageResource extends JsonResource
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
            'position' => $this->position,
            'color' => $this->color,
            'is_initial' => $this->is_initial,
            'is_final' => $this->is_final,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
            'active' => $this->active,
        ];
    }
}
