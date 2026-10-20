<?php

namespace App\Modules\Commission\Http\Resources;

use App\Modules\Commission\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Commission
 */
class CommissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'base_amount_cents' => $this->base_amount_cents,
            'base_amount' => $this->formatAmount($this->base_amount_cents),
            'amount_cents' => $this->amount_cents,
            'amount' => $this->formatAmount($this->amount_cents),
            'breakdown' => $this->breakdown ?? [],
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'service_order' => $this->whenLoaded('serviceOrder', fn () => $this->serviceOrder ? [
                'id' => $this->serviceOrder->uuid,
                'number' => $this->serviceOrder->number,
                'code' => $this->serviceOrder->code,
                'status' => $this->serviceOrder->status?->value,
                'status_label' => $this->serviceOrder->status?->label(),
                'client' => $this->serviceOrder->relationLoaded('client') && $this->serviceOrder->client
                    ? [
                        'id' => $this->serviceOrder->client->uuid,
                        'name' => $this->serviceOrder->client->name,
                    ]
                    : null,
            ] : null),
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? [
                'id' => $this->technician->uuid,
                'name' => $this->technician->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function formatAmount(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }
}
