<?php

namespace App\Modules\Checklist\Http\Resources;

use App\Modules\Checklist\Models\ServiceOrderChecklistAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderChecklistAnswer
 */
class ServiceOrderChecklistAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'text_answer' => $this->text_answer,
            'option_ids' => $this->whenLoaded('selectedOptions', fn () => $this->selectedOptions
                ->pluck('uuid')
                ->values()),
            'photos' => ServiceOrderChecklistPhotoResource::collection($this->whenLoaded('photos')),
            'answered_at' => $this->answered_at?->toIso8601String(),
            'answered_by' => $this->whenLoaded('answeredBy', fn () => $this->answeredBy ? [
                'id' => $this->answeredBy->uuid,
                'name' => $this->answeredBy->name,
            ] : null),
        ];
    }
}
