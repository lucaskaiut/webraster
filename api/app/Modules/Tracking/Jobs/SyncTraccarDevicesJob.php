<?php

namespace App\Modules\Tracking\Jobs;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\DTOs\TraccarDevice;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mantém em equipments o status/lastUpdate reportados pelo Traccar. O painel
 * usa esse sinal para considerar online o dispositivo que está se comunicando
 * mesmo quando ainda não enviou uma nova posição (ex.: pacotes de heartbeat).
 */
class SyncTraccarDevicesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 240;

    public function __construct()
    {
        $this->onQueue((string) config('traccar.sync_queue', 'sync'));
    }

    public function uniqueId(): string
    {
        return 'sync-traccar-devices';
    }

    public function handle(TraccarGateway $gateway): void
    {
        if (! $gateway->isConfigured()) {
            return;
        }

        try {
            $devices = $gateway->listDevices()
                ->keyBy(fn (TraccarDevice $device) => $device->id);
        } catch (Throwable $exception) {
            Log::warning('traccar.devices_sync_failed', [
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if ($devices->isEmpty()) {
            return;
        }

        Equipment::query()
            ->withoutTenancy()
            ->whereIn('traccar_device_id', $devices->keys()->all())
            ->chunkById(200, function (Collection $equipments) use ($devices): void {
                foreach ($equipments as $equipment) {
                    $device = $devices->get((int) $equipment->traccar_device_id);

                    if (! $device instanceof TraccarDevice) {
                        continue;
                    }

                    $equipment->forceFill([
                        'traccar_status' => $device->status,
                        'traccar_last_update' => $device->lastUpdate,
                    ]);

                    if ($equipment->isDirty()) {
                        $equipment->save();
                    }
                }
            });
    }
}
