<?php

namespace App\Modules\DeviceCommand\Http\Resources;

use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceCommandLog
 */
class DeviceCommandLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'command_type' => $this->command_type,
            'payload' => $this->payload,
            'status' => $this->status?->value,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'response' => $this->response,
            'vehicle_id' => $this->vehicle?->uuid,
            'equipment_id' => $this->equipment?->uuid,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
