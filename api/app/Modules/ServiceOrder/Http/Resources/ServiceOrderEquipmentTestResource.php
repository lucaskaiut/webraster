<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\ServiceOrder\Models\ServiceOrderEquipmentTest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderEquipmentTest
 */
class ServiceOrderEquipmentTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actionLabel = match ($this->action) {
            'block' => 'Bloqueio',
            'unblock' => 'Desbloqueio',
            'ignition' => 'Ignição',
            'location' => 'Localização',
            default => $this->action,
        };

        $statusLabel = match ($this->status) {
            DeviceCommandStatus::PENDING => 'Pendente',
            DeviceCommandStatus::SUCCESS => 'Sucesso',
            DeviceCommandStatus::FAILED => 'Falha',
            default => $this->status?->value,
        };

        $result = is_array($this->result) ? $this->result : [];
        $ignition = array_key_exists('ignition', $result) ? $result['ignition'] : null;
        $latitude = array_key_exists('latitude', $result) ? $result['latitude'] : null;
        $longitude = array_key_exists('longitude', $result) ? $result['longitude'] : null;

        return [
            'id' => $this->uuid,
            'action' => $this->action,
            'action_label' => $actionLabel,
            'command_type' => $this->command_type,
            'status' => $this->status?->value,
            'status_label' => $statusLabel,
            'ignition' => $ignition === null ? null : (bool) $ignition,
            'latitude' => $latitude === null ? null : (float) $latitude,
            'longitude' => $longitude === null ? null : (float) $longitude,
            'response' => $this->result,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->uuid,
                'name' => $this->user->name,
            ] : null),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->uuid,
                'imei' => $this->equipment->imei,
                'model' => $this->equipment->model,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
