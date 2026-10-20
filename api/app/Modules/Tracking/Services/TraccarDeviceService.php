<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\DTOs\TraccarDevice;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TraccarDeviceService
{
    public function __construct(private readonly TraccarGateway $traccar) {}

    /**
     * Garante que o equipamento exista no Traccar (cria ou vincula pelo IMEI).
     *
     * Falhas do Traccar são registradas no log e não impedem o cadastro do
     * equipamento no sistema. Nesse caso, o vínculo pode ser refeito depois.
     */
    public function sync(Equipment $equipment, bool $identityChanged = true): bool
    {
        if (! $this->traccar->isConfigured() || ! $identityChanged) {
            return false;
        }

        $name = filled($equipment->model) ? (string) $equipment->model : $equipment->imei;

        try {
            $device = $this->resolveDevice($equipment, $name);
        } catch (Exception $exception) {
            Log::warning('traccar.device_sync_failed', [
                'imei' => $equipment->imei,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if ((int) $equipment->traccar_device_id !== $device->id) {
            $equipment->forceFill(['traccar_device_id' => $device->id])->save();
        }

        return true;
    }

    private function resolveDevice(Equipment $equipment, string $name): TraccarDevice
    {
        if ($equipment->traccar_device_id) {
            try {
                return $this->traccar->updateDevice(
                    (int) $equipment->traccar_device_id,
                    $equipment->imei,
                    $name,
                    $equipment->model,
                );
            } catch (RuntimeException) {
                // Dispositivo pode ter sido removido no Traccar; recria ou relinca pelo IMEI.
            }
        }

        $existing = $this->traccar->findDeviceByUniqueId($equipment->imei);

        if ($existing instanceof TraccarDevice) {
            return $existing;
        }

        try {
            return $this->traccar->createDevice($equipment->imei, $name, $equipment->model);
        } catch (RuntimeException $exception) {
            $existing = $this->traccar->findDeviceByUniqueId($equipment->imei);

            if ($existing instanceof TraccarDevice) {
                return $existing;
            }

            throw $exception;
        }
    }
}
