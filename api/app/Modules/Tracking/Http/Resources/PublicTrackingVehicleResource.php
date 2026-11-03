<?php

namespace App\Modules\Tracking\Http\Resources;

use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Veículo no link público de monitoramento: apenas o necessário para o mapa
 * (sem cliente, motorista, equipamento ou dados técnicos).
 */
class PublicTrackingVehicleResource extends JsonResource
{
    public function __construct(
        Vehicle $vehicle,
        private readonly ?GpsPosition $position,
        private readonly bool $online,
    ) {
        parent::__construct($vehicle);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->resource;
        $position = $this->position;

        return [
            'id' => $vehicle->uuid,
            'plate' => $vehicle->plate,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'color' => $vehicle->color,
            'vehicle_type' => $vehicle->vehicle_type ? (int) $vehicle->vehicle_type : null,
            'online' => $this->online,
            'position' => $position !== null
                ? [
                    'id' => $position->uuid,
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'address' => $position->address,
                    'recorded_at' => $position->recorded_at?->toIso8601String(),
                    'speed' => $position->speed,
                    'heading' => $position->heading,
                    'ignition' => $position->ignition,
                    'motion' => is_array($position->attributes) && array_key_exists('motion', $position->attributes)
                        ? (bool) $position->attributes['motion']
                        : null,
                ]
                : null,
        ];
    }
}
