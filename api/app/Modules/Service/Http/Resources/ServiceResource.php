<?php

namespace App\Modules\Service\Http\Resources;

use App\Modules\Service\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'amount_cents' => $this->amount_cents,
            'amount' => number_format($this->amount_cents / 100, 2, '.', ''),
            'linked_services' => $this->whenLoaded('linkedServices', fn () => $this->linkedServices
                ->map(fn (Service $linked) => [
                    'id' => $linked->uuid,
                    'name' => $linked->name,
                    'amount_cents' => $linked->amount_cents,
                    'amount' => number_format($linked->amount_cents / 100, 2, '.', ''),
                ])
                ->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
