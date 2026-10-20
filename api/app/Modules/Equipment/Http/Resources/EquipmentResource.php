<?php

namespace App\Modules\Equipment\Http\Resources;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Stock\Http\Resources\StockResource;
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
        $canViewDetails = (bool) $request->user()?->hasPermission(Permission::EQUIPMENT_DETAILS_READ);

        return [
            'id' => $this->uuid,
            'vehicle_id' => $this->vehicle?->uuid,
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'stock_id' => $this->whenLoaded('stock', fn () => $this->stock?->uuid),
            'stock' => StockResource::make($this->whenLoaded('stock')),
            'imei' => $canViewDetails ? $this->imei : null,
            'model' => $canViewDetails ? $this->model : null,
            'line_number' => $canViewDetails ? $this->line_number : null,
            'carrier' => $canViewDetails ? $this->carrier : null,
            'is_active' => (bool) $this->is_active,
            'is_assigned' => $this->vehicle_id !== null,
            'traccar_status' => $this->traccar_status,
            'traccar_last_update' => $this->traccar_last_update?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
