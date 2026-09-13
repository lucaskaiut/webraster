<?php

namespace App\Modules\Client\Http\Resources;

use App\Modules\Client\Models\ClientOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientOrder
 */
class ClientOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'client_id' => $this->client?->uuid ?? $this->client()->value('uuid'),
            'due_day' => $this->due_day,
            'periodicity' => $this->periodicity?->value,
            'periodicity_label' => $this->periodicity?->label(),
            'total_cents' => $this->total_cents,
            'total' => number_format($this->total_cents / 100, 2, '.', ''),
            'subscription_id' => $this->subscription?->uuid,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) {
                return [
                    'id' => $item->getKey(),
                    'service_id' => $item->service?->uuid,
                    'service_name' => $item->service_name,
                    'unit_amount_cents' => $item->unit_amount_cents,
                    'unit_amount' => number_format($item->unit_amount_cents / 100, 2, '.', ''),
                    'quantity' => $item->quantity,
                    'line_total_cents' => $item->line_total_cents,
                    'line_total' => number_format($item->line_total_cents / 100, 2, '.', ''),
                    'vehicles' => $item->relationLoaded('vehicles')
                        ? $item->vehicles->map(fn ($vehicle) => [
                            'id' => $vehicle->uuid,
                            'plate' => $vehicle->plate,
                            'brand' => $vehicle->brand,
                            'model' => $vehicle->model,
                        ])->values()
                        : [],
                ];
            })->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
