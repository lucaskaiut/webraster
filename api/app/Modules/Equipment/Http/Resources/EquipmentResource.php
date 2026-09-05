<?php

namespace App\Modules\Equipment\Http\Resources;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Vehicle\Http\Resources\VehicleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Equipment
 */
class EquipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'vehicle_id' => $this->vehicle?->uuid,
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'imei' => $this->imei,
            'model' => $this->model,
            'iccid' => $this->iccid,
            'carrier' => $this->carrier,
            'is_active' => (bool) $this->is_active,
            'is_assigned' => $this->vehicle_id !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
