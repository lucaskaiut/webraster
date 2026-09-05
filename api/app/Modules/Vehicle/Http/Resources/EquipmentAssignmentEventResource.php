<?php

namespace App\Modules\Vehicle\Http\Resources;

use App\Modules\Equipment\Http\Resources\EquipmentResource;
use App\Modules\Vehicle\Models\EquipmentAssignmentEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentAssignmentEvent
 */
class EquipmentAssignmentEventResource extends JsonResource
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
            'equipment_id' => $this->equipment?->uuid,
            'equipment' => EquipmentResource::make($this->whenLoaded('equipment')),
            'previous_equipment_id' => $this->previousEquipment?->uuid,
            'previous_equipment' => EquipmentResource::make($this->whenLoaded('previousEquipment')),
            'event' => $this->event->value,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
