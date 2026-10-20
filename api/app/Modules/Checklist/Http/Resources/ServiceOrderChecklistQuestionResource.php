<?php

namespace App\Modules\Checklist\Http\Resources;

use App\Modules\Checklist\Models\ServiceOrderChecklistQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderChecklistQuestion
 */
class ServiceOrderChecklistQuestionResource extends JsonResource
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
            'options' => ServiceOrderChecklistOptionResource::collection($this->whenLoaded('options')),
            'answer' => $this->whenLoaded(
                'answer',
                fn () => $this->answer
                    ? ServiceOrderChecklistAnswerResource::make($this->answer)
                    : null,
            ),
        ];
    }
}
