<?php

namespace App\Modules\DeviceCommand\Services;

use App\Integrations\Traccar\TraccarCommandService;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\User\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeviceCommandService
{
    public function __construct(
        private readonly TraccarCommandService $commands,
        private readonly TraccarGateway $traccar,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function availableCommands(Equipment $equipment): Collection
    {
        $traccarDeviceId = $this->resolveTraccarDeviceId($equipment);

        return $this->commands->listTypes($traccarDeviceId)
            ->map(fn ($type) => $type->toArray())
            ->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function send(Equipment $equipment, string $type, array $attributes, User $user): DeviceCommandLog
    {
        $traccarDeviceId = $this->resolveTraccarDeviceId($equipment);

        if (! $this->commands->supports($traccarDeviceId, $type, fresh: true)) {
            throw ValidationException::withMessages([
                'type' => ['Command not supported by device.'],
            ]);
        }

        $payload = [
            'type' => $type,
            'attributes' => $attributes,
            'deviceId' => $traccarDeviceId,
        ];

        $log = new DeviceCommandLog;
        $log->forceFill([
            'tenant_id' => $equipment->tenant_id,
            'vehicle_id' => $equipment->vehicle_id,
            'equipment_id' => $equipment->getKey(),
            'traccar_device_id' => $traccarDeviceId,
            'user_id' => $user->getKey(),
            'command_type' => $type,
            'payload' => $payload,
            'status' => DeviceCommandStatus::PENDING,
            'requested_at' => now(),
        ])->save();

        try {
            $result = $this->commands->send($traccarDeviceId, $type, $attributes);

            $log->forceFill([
                'status' => $result->successful
                    ? DeviceCommandStatus::SUCCESS
                    : DeviceCommandStatus::FAILED,
                'executed_at' => now(),
                'response' => [
                    'http_status' => $result->status,
                    'body' => $result->body,
                    'raw' => $result->rawBody !== null ? mb_substr($result->rawBody, 0, 2000) : null,
                ],
            ])->save();

            if (! $result->successful) {
                throw ValidationException::withMessages([
                    'type' => ['Falha ao enviar comando ao Traccar.'],
                ]);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $log->forceFill([
                'status' => DeviceCommandStatus::FAILED,
                'executed_at' => now(),
                'response' => [
                    'error' => $exception->getMessage(),
                ],
            ])->save();

            throw ValidationException::withMessages([
                'type' => [$exception->getMessage() ?: 'Falha ao enviar comando ao Traccar.'],
            ]);
        }

        return $log->fresh() ?? $log;
    }

    private function resolveTraccarDeviceId(Equipment $equipment): int
    {
        if (! $this->traccar->isConfigured()) {
            throw ValidationException::withMessages([
                'type' => ['Integração Traccar não configurada.'],
            ]);
        }

        if ($equipment->traccar_device_id) {
            return (int) $equipment->traccar_device_id;
        }

        try {
            $device = $this->traccar->findDeviceByUniqueId($equipment->imei);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'type' => [$exception->getMessage()],
            ]);
        }

        if ($device === null) {
            throw ValidationException::withMessages([
                'type' => ['Dispositivo não encontrado no Traccar.'],
            ]);
        }

        $equipment->forceFill(['traccar_device_id' => $device->id])->save();

        return (int) $device->id;
    }
}
