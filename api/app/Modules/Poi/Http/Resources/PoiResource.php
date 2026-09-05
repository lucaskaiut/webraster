<?php

namespace App\Modules\Poi\Http\Resources;

use App\Modules\Client\Http\Resources\ClientResource;
use App\Modules\Poi\Models\Poi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Poi
 */
class PoiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'client_id' => $this->client?->uuid,
            'client' => ClientResource::make($this->whenLoaded('client')),
            'category_id' => $this->category?->uuid,
            'category' => PoiCategoryResource::make($this->whenLoaded('category')),
            'name' => $this->name,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
