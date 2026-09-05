<?php

namespace App\Modules\Geofence\Http\Resources;

use App\Modules\Client\Http\Resources\ClientResource;
use App\Modules\Geofence\Models\Geofence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Geofence
 */
class GeofenceResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type?->value,
            'is_active' => (bool) $this->is_active,
            'center_latitude' => $this->center_latitude,
            'center_longitude' => $this->center_longitude,
            'radius_meters' => $this->radius_meters,
            'geometry' => $this->geometry,
            'bbox' => [
                'min_lat' => $this->bbox_min_lat,
                'max_lat' => $this->bbox_max_lat,
                'min_lng' => $this->bbox_min_lng,
                'max_lng' => $this->bbox_max_lng,
            ],
            'events_count' => $this->whenCounted('events'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
