<?php

namespace App\Modules\Equipment\Http\Resources;

use App\Modules\Equipment\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Equipamento disponível para o técnico selecionar durante a OS.
 *
 * Não expõe estoque, cliente ou vínculos administrativos.
 *
 * @mixin Equipment
 */
class TechnicianAvailableEquipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'imei' => $this->imei,
            'model' => $this->model,
            'line_number' => $this->line_number,
            'carrier' => $this->carrier,
        ];
    }
}
