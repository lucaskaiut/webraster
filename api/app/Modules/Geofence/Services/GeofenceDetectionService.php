<?php

namespace App\Modules\Geofence\Services;

use App\Modules\Geofence\Enums\GeofenceEventType;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Models\GeofenceEvent;
use App\Modules\Geofence\Models\VehicleGeofenceState;
use App\Modules\Tracking\Models\GpsPosition;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Detecta entrada/saída de geocercas a partir de posições GPS.
 *
 * Estratégia:
 * - Usa recorded_at da posição como ordenação principal.
 * - Ignora posições com timestamp anterior ao último processado (fora de ordem).
 * - Idempotência via unique (gps_position_id, geofence_id, type).
 * - Pré-filtro por bounding box antes do Haversine / point-in-polygon.
 *
 * Limitação: posições atrasadas com timestamp antigo após avanço do estado
 * não reescrevem o histórico (evita eventos espúrios).
 */
class GeofenceDetectionService
{
    /**
     * @return Collection<int, GeofenceEvent>
     */
    public function process(GpsPosition $position): Collection
    {
        $events = collect();

        $geofences = Geofence::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $position->tenant_id)
            ->where('client_id', $position->client_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();

        if ($geofences->isEmpty()) {
            return $events;
        }

        foreach ($geofences as $geofence) {
            $event = $this->evaluate($position, $geofence);

            if ($event !== null) {
                $events->push($event);
            }
        }

        return $events;
    }

    private function evaluate(GpsPosition $position, Geofence $geofence): ?GeofenceEvent
    {
        $state = VehicleGeofenceState::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'tenant_id' => $position->tenant_id,
                'vehicle_id' => $position->vehicle_id,
                'geofence_id' => $geofence->getKey(),
            ], [
                'is_inside' => false,
            ]);

        $recordedAt = CarbonImmutable::parse($position->recorded_at);

        if (
            $state->exists
            && $state->last_recorded_at !== null
            && $recordedAt->lessThan(CarbonImmutable::parse($state->last_recorded_at))
        ) {
            Log::debug('geofence.skip_out_of_order', [
                'vehicle_id' => $position->vehicle_id,
                'geofence_id' => $geofence->getKey(),
                'position_id' => $position->getKey(),
                'recorded_at' => $recordedAt->toIso8601String(),
                'last_recorded_at' => CarbonImmutable::parse($state->last_recorded_at)->toIso8601String(),
            ]);

            return null;
        }

        if (
            $state->exists
            && $state->last_gps_position_id !== null
            && (int) $state->last_gps_position_id === (int) $position->getKey()
        ) {
            return null;
        }

        $inside = $geofence->contains((float) $position->latitude, (float) $position->longitude);
        $previous = (bool) $state->is_inside;
        $eventType = null;

        if (! $previous && $inside) {
            $eventType = GeofenceEventType::ENTRY;
        } elseif ($previous && ! $inside) {
            $eventType = GeofenceEventType::EXIT;
        }

        $event = null;

        if ($eventType !== null) {
            $event = $this->createEvent($position, $geofence, $eventType, $previous, $inside);
        }

        $state->forceFill([
            'tenant_id' => $position->tenant_id,
            'is_inside' => $inside,
            'last_gps_position_id' => $position->getKey(),
            'last_recorded_at' => $recordedAt,
        ])->save();

        if ($eventType !== null) {
            Log::info('geofence.transition', [
                'vehicle_id' => $position->vehicle_id,
                'geofence_id' => $geofence->getKey(),
                'position_id' => $position->getKey(),
                'previous_state' => $previous ? 'inside' : 'outside',
                'current_state' => $inside ? 'inside' : 'outside',
                'event_generated' => $eventType->value,
                'event_persisted' => $event !== null,
            ]);
        }

        return $event;
    }

    private function createEvent(
        GpsPosition $position,
        Geofence $geofence,
        GeofenceEventType $type,
        bool $previous,
        bool $inside,
    ): ?GeofenceEvent {
        try {
            $event = new GeofenceEvent;
            $event->forceFill([
                'tenant_id' => $position->tenant_id,
                'client_id' => $position->client_id,
                'vehicle_id' => $position->vehicle_id,
                'geofence_id' => $geofence->getKey(),
                'gps_position_id' => $position->getKey(),
                'type' => $type,
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'recorded_at' => $position->recorded_at,
                'processed_at' => now(),
                'speed' => $position->speed,
                'meta' => [
                    'previous_state' => $previous ? 'inside' : 'outside',
                    'current_state' => $inside ? 'inside' : 'outside',
                    'equipment_id' => $position->equipment_id,
                ],
            ])->save();

            return $event;
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                Log::debug('geofence.duplicate_event_skipped', [
                    'vehicle_id' => $position->vehicle_id,
                    'geofence_id' => $geofence->getKey(),
                    'position_id' => $position->getKey(),
                    'type' => $type->value,
                ]);

                return null;
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return $driverCode === 1062
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'geofence_events_idempotent');
    }
}
