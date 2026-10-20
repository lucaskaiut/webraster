<?php

namespace App\Modules\Checklist\Http\Resources;

use App\Modules\Checklist\Models\ServiceOrderChecklistOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderChecklistOption
 */
class ServiceOrderChecklistOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'label' => $this->label,
            'position' => $this->position,
        ];
    }
}
