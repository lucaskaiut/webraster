<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use App\Modules\ServiceOrder\Models\ServiceOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderItem
 */
class ServiceOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'service_id' => $this->service?->uuid,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_amount_cents' => $this->unit_amount_cents,
            'unit_amount' => number_format($this->unit_amount_cents / 100, 2, '.', ''),
            'total_cents' => $this->totalCents(),
            'total' => number_format($this->totalCents() / 100, 2, '.', ''),
        ];
    }
}
