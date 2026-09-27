<?php

namespace App\Modules\Crm\Http\Resources;

use App\Modules\Crm\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'ai_enabled' => $this->ai_enabled,
            'stage' => $this->whenLoaded('stage', fn () => [
                'id' => $this->stage?->uuid,
                'name' => $this->stage?->name,
                'color' => $this->stage?->color,
            ]),
        ];
    }
}
