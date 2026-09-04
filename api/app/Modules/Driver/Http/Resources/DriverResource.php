<?php

namespace App\Modules\Driver\Http\Resources;

use App\Modules\Client\Http\Resources\ClientResource;
use App\Modules\Driver\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Driver
 */
class DriverResource extends JsonResource
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
            'name' => $this->name,
            'document' => $this->document,
            'phone' => $this->phone,
            'email' => $this->email,
            'cnh_number' => $this->cnh_number,
            'cnh_expires_at' => $this->cnh_expires_at?->toDateString(),
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
