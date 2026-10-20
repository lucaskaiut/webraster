<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceCommandLog
 */
class ServiceOrderEquipmentTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $action = match ($this->command_type) {
            'engineStop' => 'block',
            'engineResume' => 'unblock',
            default => $this->command_type,
        };

        $actionLabel = match ($action) {
            'block' => 'Bloqueio',
            'unblock' => 'Desbloqueio',
            default => $action,
        };

        $statusLabel = match ($this->status) {
            DeviceCommandStatus::PENDING => 'Pendente',
            DeviceCommandStatus::SUCCESS => 'Sucesso',
            DeviceCommandStatus::FAILED => 'Falha',
            default => $this->status?->value,
        };

        return [
            'id' => $this->uuid,
            'action' => $action,
            'action_label' => $actionLabel,
            'command_type' => $this->command_type,
            'status' => $this->status?->value,
            'status_label' => $statusLabel,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'response' => $this->response,
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
