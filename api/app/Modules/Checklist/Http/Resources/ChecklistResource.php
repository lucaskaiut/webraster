<?php

namespace App\Modules\Checklist\Http\Resources;

use App\Modules\Checklist\Models\Checklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Checklist
 */
class ChecklistResource extends JsonResource
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
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'is_active' => (bool) $this->is_active,
            'questions_count' => $this->whenCounted('questions'),
            'questions' => ChecklistQuestionResource::collection($this->whenLoaded('questions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
