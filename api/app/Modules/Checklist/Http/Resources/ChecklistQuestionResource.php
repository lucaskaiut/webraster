<?php

namespace App\Modules\Checklist\Http\Resources;

use App\Modules\Checklist\Models\ChecklistQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChecklistQuestion
 */
class ChecklistQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'prompt' => $this->prompt,
            'answer_type' => $this->answer_type?->value,
            'answer_type_label' => $this->answer_type?->label(),
            'photo_rule' => $this->photo_rule?->value,
            'photo_rule_label' => $this->photo_rule?->label(),
            'position' => $this->position,
            'options' => ChecklistQuestionOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
