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
            'vehicle_type' => $this->vehicle_type ? (int) $this->vehicle_type : null,
            'transmission' => $this->transmission,
            'odometer' => $this->odometer,
            'max_speed_kmh' => $this->max_speed_kmh,
            'speed_hysteresis_percent' => $this->speed_hysteresis_percent,
            'speed_min_duration_seconds' => $this->speed_min_duration_seconds,
            'average_consumption' => $this->average_consumption,
            'tank_capacity' => $this->tank_capacity,
            'crlv_file' => $this->crlv_file,
            'crlv_file_url' => $this->crlv_file ? asset("storage/{$this->crlv_file}") : null,
            'fipe_code' => $this->fipe_code,
            'fipe_model_year' => $this->fipe_model_year,
            'fipe_fuel' => $this->fipe_fuel,
            'fipe_reference_month' => $this->fipe_reference_month,
            'fipe_value' => $this->fipe_value,
            'fipe_model' => $this->fipe_model,
            'fipe_brand' => $this->fipe_brand,
            'fipe_score' => $this->fipe_score,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
