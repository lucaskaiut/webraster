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
            'speed_excess' => $this->type?->value === 'speed' ? $this->speedExcessPayload() : null,
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

    /**
     * @return array<string, mixed>|null
     */
    private function speedExcessPayload(): ?array
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        if (! isset($meta['started_at'], $meta['ended_at'])) {
            return null;
        }

        return [
            'started_at' => $meta['started_at'],
            'ended_at' => $meta['ended_at'],
            'duration_seconds' => $meta['duration_seconds'] ?? null,
            'limit_kmh' => $meta['limit_kmh'] ?? null,
            'max_speed_kmh' => $meta['max_speed_kmh'] ?? null,
            'avg_speed_kmh' => $meta['avg_speed_kmh'] ?? null,
            'distance_meters' => $meta['distance_meters'] ?? null,
            'position_count' => $meta['position_count'] ?? null,
            'start_latitude' => $meta['start_latitude'] ?? $this->latitude,
            'start_longitude' => $meta['start_longitude'] ?? $this->longitude,
            'end_latitude' => $meta['end_latitude'] ?? null,
            'end_longitude' => $meta['end_longitude'] ?? null,
        ];
    }
}
