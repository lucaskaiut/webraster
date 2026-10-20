<?php

namespace App\Modules\Checklist\Http\Resources;

use App\Modules\Checklist\Models\ServiceOrderChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderChecklist
 */
class ServiceOrderChecklistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completed_by' => $this->whenLoaded('completedBy', fn () => $this->completedBy ? [
                'id' => $this->completedBy->uuid,
                'name' => $this->completedBy->name,
            ] : null),
            'questions' => ServiceOrderChecklistQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
