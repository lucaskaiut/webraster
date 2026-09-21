<?php

namespace App\Modules\Tracking\Http\Resources;

use App\Modules\Alert\Support\TraccarAttributeReader;
use App\Modules\Tracking\Models\GpsPosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GpsPosition
 */
class GpsPositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($this->attributes) ? $this->attributes : [];

        return [
            'id' => $this->uuid,
            'vehicle_id' => $this->vehicle?->uuid ?? null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'speed' => $this->speed,
            'ignition' => $this->ignition,
            'battery' => $this->battery,
            'heading' => $this->heading,
            'altitude' => $this->altitude,
            'motion' => array_key_exists('motion', $attributes) ? (bool) $attributes['motion'] : null,
            'odometer' => isset($attributes['totalDistance']) ? (float) $attributes['totalDistance'] : null,
            'charging' => array_key_exists('charge', $attributes) ? (bool) $attributes['charge'] : null,
            'protocol' => isset($attributes['protocol']) ? (string) $attributes['protocol'] : null,
            'valid' => $this->valid,
            'signal' => TraccarAttributeReader::signal($attributes),
            'satellites' => TraccarAttributeReader::satellites($attributes),
            'voltage' => TraccarAttributeReader::voltage($attributes),
            'blocked' => TraccarAttributeReader::isBlocked($attributes),
            'alarms' => TraccarAttributeReader::formatAlarmsForApi($attributes),
        ];
    }
}
