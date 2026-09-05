<?php

namespace App\Modules\Alert\Http\Resources;

use App\Modules\Alert\Models\AlertConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AlertConfig
 */
class AlertConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'is_enabled' => (bool) $this->is_enabled,
            'notify_in_app' => (bool) $this->notify_in_app,
            'notify_email' => (bool) $this->notify_email,
            'settings' => $this->settingsWithDefaults(),
            'client_id' => $this->client?->uuid,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->uuid,
                'name' => $this->client->name,
            ] : null),
            'vehicle_id' => $this->vehicle?->uuid,
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->uuid,
                'plate' => $this->vehicle->plate,
            ] : null),
            'scope' => $this->resource->vehicle_id ? 'vehicle' : ($this->resource->client_id ? 'client' : 'all'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
