<?php

namespace App\Modules\Client\Http\Resources;

use App\Modules\Vehicle\Http\Resources\VehicleResource;
use App\Modules\Vehicle\Models\VehicleShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleShare
 */
class SharedVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'vehicle_id' => $this->vehicle?->uuid,
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'owner_client_id' => $this->vehicle?->client?->uuid,
            'shared_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
