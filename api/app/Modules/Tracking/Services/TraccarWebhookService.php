<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Tracking\DTOs\TraccarPosition;
use Illuminate\Support\Facades\Log;

/**
 * Recebe e persiste posições encaminhadas pelo Traccar (webhook de forward).
 */
class TraccarWebhookService
{
    public function __construct(private readonly TrackingService $tracking) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $positionPayload = $payload['position'] ?? null;

        if (! is_array($positionPayload) || ! isset($positionPayload['deviceId'])) {
            Log::warning('traccar.webhook_invalid_payload');

            return;
        }

        try {
            $position = TraccarPosition::fromArray($positionPayload);
        } catch (\Throwable $exception) {
            Log::warning('traccar.webhook_parse_failed', [
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $equipment = Equipment::query()
            ->withoutTenancy()
            ->where('traccar_device_id', $position->deviceId)
            ->first();

        if ($equipment === null) {
            Log::debug('traccar.webhook_unknown_device', [
                'deviceId' => $position->deviceId,
            ]);

            return;
        }

        $vehicle = $equipment->vehicle;

        if ($vehicle === null) {
            Log::debug('traccar.webhook_unassigned_device', [
                'deviceId' => $position->deviceId,
            ]);

            return;
        }

        TenantContext::set($equipment->tenant);

        try {
            $this->tracking->persistPosition($vehicle, $equipment, $position);
        } catch (\Throwable $exception) {
            Log::warning('traccar.webhook_persist_failed', [
                'deviceId' => $position->deviceId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
