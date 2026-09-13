<?php

namespace App\Modules\VehicleData\Http\Resources;

use App\Modules\VehicleData\DTOs\VehicleDataDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleDataDTO
 */
class VehicleDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'plate' => $this->plate,
            'brand' => $this->brand,
            'model' => $this->model,
            'submodel' => $this->submodel,
            'version' => $this->version,
            'year' => $this->year,
            'model_year' => $this->modelYear,
            'color' => $this->color,
            'chassis' => $this->chassis,
            'fuel' => $this->fuel,
            'transmission' => $this->transmission,
            'segment' => $this->segment,
            'municipality' => $this->municipality,
            'uf' => $this->uf,
            'situation' => $this->situation,
            'fipe' => $this->fipe !== null ? [
                'code' => $this->fipe->code,
                'model_year' => $this->fipe->modelYear,
                'fuel' => $this->fipe->fuel,
                'reference_month' => $this->fipe->referenceMonth,
                'value' => $this->fipe->value,
                'model' => $this->fipe->model,
                'brand' => $this->fipe->brand,
                'score' => $this->fipe->score,
            ] : null,
            'extra' => $this->extra,
        ];
    }
}
