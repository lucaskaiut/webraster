<?php

namespace App\Modules\Crm\Http\Resources;

use App\Modules\Crm\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'source' => $this->source,
            'score' => $this->score,
            'notes' => $this->notes,
            'ai_enabled' => $this->ai_enabled,
            'last_interaction_at' => $this->last_interaction_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'contact' => [
                'id' => $this->contact?->uuid,
                'name' => $this->contact?->name,
                'phone' => $this->contact?->phone,
                'email' => $this->contact?->email,
                'avatar_url' => $this->contact?->avatar_url,
            ],
            'pipeline' => $this->whenLoaded('pipeline', fn () => [
                'id' => $this->pipeline?->uuid,
                'name' => $this->pipeline?->name,
            ]),
            'stage' => $this->whenLoaded('stage', fn () => [
                'id' => $this->stage?->uuid,
                'name' => $this->stage?->name,
                'color' => $this->stage?->color,
            ]),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->uuid,
                'name' => $this->owner?->name,
            ]),
        ];
    }
}
