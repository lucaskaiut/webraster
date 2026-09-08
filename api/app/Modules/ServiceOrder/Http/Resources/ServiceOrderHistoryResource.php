<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use App\Modules\ServiceOrder\Models\ServiceOrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderHistory
 */
class ServiceOrderHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'action' => $this->action?->value,
            'field' => $this->field,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'meta' => $this->meta,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->uuid,
                'name' => $this->user->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
