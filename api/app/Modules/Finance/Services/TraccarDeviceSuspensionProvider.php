<?php

namespace App\Modules\Finance\Services;

use App\Integrations\Traccar\TraccarCommandService;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Contracts\DeviceSuspensionProvider;
use Illuminate\Support\Facades\Log;

class TraccarDeviceSuspensionProvider implements DeviceSuspensionProvider
{
    public function __construct(
        private readonly TraccarCommandService $commands,
    ) {}

    public function suspend(Equipment $equipment): void
    {
        $equipment->forceFill(['billing_suspended_at' => now()])->save();

        $this->tryCommand($equipment, 'engineStop');
    }

    public function unsuspend(Equipment $equipment): void
    {
        $equipment->forceFill(['billing_suspended_at' => null])->save();

        $this->tryCommand($equipment, 'engineResume');
    }

    private function tryCommand(Equipment $equipment, string $type): void
    {
        if (! $equipment->traccar_device_id) {
            return;
        }

        try {
            $deviceId = (int) $equipment->traccar_device_id;

            if (! $this->commands->supports($deviceId, $type, fresh: false)) {
                return;
            }

            $this->commands->send($deviceId, $type);
        } catch (\Throwable $exception) {
            Log::warning('finance.device_suspension.command_failed', [
                'equipment_id' => $equipment->getKey(),
                'command' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
