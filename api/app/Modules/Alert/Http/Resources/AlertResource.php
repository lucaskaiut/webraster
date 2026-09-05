<?php

namespace App\Modules\Alert\Http\Resources;

use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Support\SpeedConverter;
use App\Modules\Client\Http\Resources\ClientResource;
use App\Modules\Vehicle\Http\Resources\VehicleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
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
            'severity' => $this->severity?->value,
            'status' => $this->status?->value,
            'title' => $this->title,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed' => $this->speed,
            'speed_kmh' => SpeedConverter::knotsToKmh($this->speed),
            'meta' => $this->meta,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'vehicle_id' => $this->vehicle?->uuid,
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'client_id' => $this->client?->uuid,
            'client' => ClientResource::make($this->whenLoaded('client')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
