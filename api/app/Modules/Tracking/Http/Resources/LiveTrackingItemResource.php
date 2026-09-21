<?php

namespace App\Modules\Tracking\Http\Resources;

use App\Modules\Client\Http\Resources\ClientResource;
use App\Modules\Equipment\Http\Resources\EquipmentResource;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Http\Resources\VehicleImageResource;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveTrackingItemResource extends JsonResource
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

        return [
            'id' => $vehicle->uuid,
            'plate' => $vehicle->plate,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'color' => $vehicle->color,
            'year' => $vehicle->year,
            'vehicle_type' => $vehicle->vehicle_type ? (int) $vehicle->vehicle_type : null,
            'client_id' => $vehicle->client?->uuid,
            'client' => $vehicle->relationLoaded('client')
                ? ClientResource::make($vehicle->client)
                : null,
            'equipment' => $vehicle->relationLoaded('equipment')
                ? EquipmentResource::make($vehicle->equipment)
                : null,
            'images' => $vehicle->relationLoaded('images')
                ? VehicleImageResource::collection($vehicle->images)
                : [],
            'online' => $this->online,
            'position' => $this->position !== null
                ? GpsPositionResource::make($this->position)
                : null,
        ];
    }
}
