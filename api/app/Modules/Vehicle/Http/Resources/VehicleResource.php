<?php

namespace App\Modules\Vehicle\Http\Resources;

use App\Modules\Client\Http\Resources\ClientResource;
use App\Modules\Equipment\Http\Resources\EquipmentResource;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
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
            'equipment' => EquipmentResource::make($this->whenLoaded('equipment')),
            'plate' => $this->plate,
            'chassis' => $this->chassis,
            'renavam' => $this->renavam,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'year' => $this->year,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
