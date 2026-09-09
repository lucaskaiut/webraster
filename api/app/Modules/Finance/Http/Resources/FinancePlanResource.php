<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancePlan
 */
class FinancePlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'amount' => number_format($this->amount_cents / 100, 2, '.', ''),
            'periodicity' => $this->periodicity?->value,
            'periodicity_label' => $this->periodicity?->label(),
            'device_limit' => $this->device_limit,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
