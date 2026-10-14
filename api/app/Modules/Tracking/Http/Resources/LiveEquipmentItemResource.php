<?php

namespace App\Modules\Tracking\Http\Resources;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Equipamento sem veículo com a última posição conhecida (estoque).
 *
 * @mixin Equipment
 */
class LiveEquipmentItemResource extends JsonResource
{
    public function __construct(
        Equipment $equipment,
        private readonly ?GpsPosition $position,
        private readonly bool $online,
    ) {
        parent::__construct($equipment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Equipment $equipment */
        $equipment = $this->resource;
        $canViewDetails = (bool) $request->user()?->hasPermission(Permission::EQUIPMENT_DETAILS_READ);

        return [
            'id' => $equipment->uuid,
            'imei' => $canViewDetails ? $equipment->imei : null,
            'model' => $canViewDetails ? $equipment->model : null,
            'line_number' => $canViewDetails ? $equipment->line_number : null,
            'carrier' => $canViewDetails ? $equipment->carrier : null,
            'online' => $this->online,
            'position' => $this->position !== null
                ? GpsPositionResource::make($this->position)
                : null,
        ];
    }
}
