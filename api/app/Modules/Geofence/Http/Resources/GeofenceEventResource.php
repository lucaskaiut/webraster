<?php

namespace App\Modules\Geofence\Http\Resources;

use App\Modules\Client\Http\Resources\ClientResource;
use App\Modules\Geofence\Models\GeofenceEvent;
use App\Modules\Vehicle\Http\Resources\VehicleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GeofenceEvent
 */
class GeofenceEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type?->value,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed' => $this->speed,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'meta' => $this->meta,
            'client_id' => $this->client?->uuid ?? $this->whenLoaded('client', fn () => $this->client?->uuid),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'vehicle_id' => $this->vehicle?->uuid,
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'geofence_id' => $this->geofence?->uuid,
            'geofence' => GeofenceResource::make($this->whenLoaded('geofence')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
