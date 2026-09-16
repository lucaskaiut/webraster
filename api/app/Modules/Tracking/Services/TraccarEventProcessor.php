<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Tracking\DTOs\TraccarPosition;
use App\Modules\Tracking\Models\TraccarEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processa um evento da inbox do Traccar: resolve equipamento/veículo,
 * persiste a posição e dispara geocercas/alertas (via TrackingService).
 */
class TraccarEventProcessor
{
    public function __construct(private readonly TrackingService $tracking) {}

    public function process(TraccarEvent $event): void
    {
        if ($event->status->isTerminal()) {
            return;
        }

        $event->markProcessing();

        $positionPayload = $event->payload['position'] ?? null;

        if (! is_array($positionPayload) || ! isset($positionPayload['deviceId'])) {
            $event->markIgnored('non_position_payload');

            return;
        }

        try {
            $position = TraccarPosition::fromArray($positionPayload);
        } catch (Throwable $exception) {
            $event->markFailed('parse_failed: '.$exception->getMessage());

            Log::warning('traccar.event_parse_failed', [
                'event_id' => $event->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $equipment = $this->resolveEquipment($event, $position);

        if ($equipment === null) {
            $event->markIgnored('unknown_device');

            return;
        }

        $vehicle = $equipment->vehicle;

        if ($vehicle === null) {
            $event->markIgnored('unassigned_device');

            return;
        }

        $event->forceFill([
            'tenant_id' => $equipment->tenant_id,
            'traccar_device_id' => $position->deviceId,
        ])->save();

        try {
            TenantContext::set($equipment->tenant);

            $this->tracking->persistPosition($vehicle, $equipment, $position);
        } catch (Throwable $exception) {
            $event->recordError($exception->getMessage());

            throw $exception;
        } finally {
            TenantContext::forget();
        }

        $event->markProcessed();
    }

    private function resolveEquipment(TraccarEvent $event, TraccarPosition $position): ?Equipment
    {
        $equipment = Equipment::query()
            ->withoutTenancy()
            ->where('traccar_device_id', $position->deviceId)
            ->first();

        if ($equipment !== null) {
            return $equipment;
        }

        $uniqueId = (string) ($event->payload['device']['uniqueId'] ?? '');

        if ($uniqueId === '') {
            return null;
        }

        return Equipment::query()
            ->withoutTenancy()
            ->where('imei', $uniqueId)
            ->first();
    }
}
