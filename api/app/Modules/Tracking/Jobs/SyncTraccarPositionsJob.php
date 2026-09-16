<?php

namespace App\Modules\Tracking\Jobs;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Tracking\Services\TrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciliação: busca no Traccar as posições que o webhook não entregou
 * (ou entregou com id=0) e persiste pelo mesmo pipeline idempotente.
 */
class SyncTraccarPositionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(TraccarGateway $gateway, TrackingService $tracking): void
    {
        if (! $gateway->isConfigured()) {
            return;
        }

        Equipment::query()
            ->withoutTenancy()
            ->where('is_active', true)
            ->whereNotNull('traccar_device_id')
            ->whereNotNull('vehicle_id')
            ->orderBy('id')
            ->chunkById(50, function (Collection $equipments) use ($gateway, $tracking): void {
                foreach ($equipments as $equipment) {
                    $this->syncEquipment($equipment, $gateway, $tracking);
                }
            });
    }

    private function syncEquipment(Equipment $equipment, TraccarGateway $gateway, TrackingService $tracking): void
    {
        try {
            TenantContext::set($equipment->tenant);

            $vehicle = $equipment->vehicle;

            if ($vehicle === null) {
                return;
            }

            $lastRecordedAt = GpsPosition::query()
                ->withoutGlobalScopes()
                ->where('equipment_id', $equipment->getKey())
                ->max('recorded_at');

            $from = $lastRecordedAt !== null
                ? CarbonImmutable::parse((string) $lastRecordedAt)->subMinutes(5)
                : CarbonImmutable::now()->subDay();

            $positions = $gateway->positionHistory(
                (int) $equipment->traccar_device_id,
                $from,
                CarbonImmutable::now(),
            );

            foreach ($positions as $position) {
                $tracking->persistPosition($vehicle, $equipment, $position);
            }
        } catch (Throwable $exception) {
            Log::warning('traccar.sync_failed', [
                'equipment_id' => $equipment->getKey(),
                'message' => $exception->getMessage(),
            ]);
        } finally {
            TenantContext::forget();
        }
    }
}
