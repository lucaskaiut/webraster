<?php

namespace App\Modules\Stock\Http\Resources;

use App\Modules\Stock\Models\EquipmentStockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentStockMovement
 */
class EquipmentStockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'from_stock' => $this->fromStock
                ? ['id' => $this->fromStock->uuid, 'name' => $this->fromStock->name]
                : null,
            'to_stock' => $this->toStock
                ? ['id' => $this->toStock->uuid, 'name' => $this->toStock->name]
                : null,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->description,
            'moved_by' => $this->movedBy
                ? ['id' => $this->movedBy->uuid, 'name' => $this->movedBy->name]
                : null,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
