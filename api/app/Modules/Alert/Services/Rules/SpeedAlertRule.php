<?php

namespace App\Modules\Alert\Services\Rules;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Models\AlertState;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Alert\Services\AlertDispatcher;
use App\Modules\Alert\Services\AlertStateStore;
use App\Modules\Alert\Support\SpeedConverter;
use App\Modules\Alert\Support\SpeedExcessSettings;
use App\Modules\Geofence\Support\GeoMath;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SpeedAlertRule implements AlertRule
{
    public function __construct(
        private readonly AlertConfigService $configs,
        private readonly AlertStateStore $states,
        private readonly AlertDispatcher $dispatcher,
    ) {}

    public function evaluate(Vehicle $vehicle, GpsPosition $position): Collection
    {
        $events = collect();
        $speedKmh = SpeedConverter::knotsToKmh($position->speed);

        if ($speedKmh === null) {
            return $events;
        }

        $config = $this->configs->matchingForVehicle($vehicle, AlertType::SPEED, false)->first();
        $settings = SpeedExcessSettings::forVehicle($vehicle, $config);

        if ($settings === null) {
            return $events;
        }

        $recordedAt = CarbonImmutable::parse($position->recorded_at);
        $state = $this->states->get(
            (int) $vehicle->tenant_id,
            (int) $vehicle->getKey(),
            AlertType::SPEED,
            0,
        );

        if ($state->is_active) {
            if ($speedKmh < $settings->closeThreshold()) {
                $alert = $this->finalizeEpisode($state, $vehicle, $position, $settings, $config, $recordedAt);
                $this->states->deactivate($state, $position);

                if ($alert !== null) {
                    $events->push($alert);
                }

                return $events;
            }

            $this->accumulateEpisode($state, $position, $speedKmh);

            return $events;
        }

        if ($speedKmh > $settings->openThreshold()) {
            $this->openEpisode($state, $position, $speedKmh, $settings, $recordedAt);
        }

        return $events;
    }

    private function openEpisode(
        AlertState $state,
        GpsPosition $position,
        float $speedKmh,
        SpeedExcessSettings $settings,
        CarbonImmutable $recordedAt,
    ): void {
        $this->states->activate($state, $position, [
            'limit_kmh' => $settings->limitKmh,
            'hysteresis_percent' => $settings->hysteresisPercent,
            'min_duration_seconds' => $settings->minDurationSeconds,
            'start_speed_kmh' => $speedKmh,
            'max_speed_kmh' => $speedKmh,
            'speed_sum' => $speedKmh,
            'position_count' => 1,
            'distance_meters' => 0.0,
            'start_latitude' => $position->latitude,
            'start_longitude' => $position->longitude,
            'last_latitude' => $position->latitude,
            'last_longitude' => $position->longitude,
            'start_gps_position_id' => $position->getKey(),
        ], $recordedAt);
    }

    private function accumulateEpisode(AlertState $state, GpsPosition $position, float $speedKmh): void
    {
        $meta = is_array($state->meta) ? $state->meta : [];
        $lastLat = (float) ($meta['last_latitude'] ?? $position->latitude);
        $lastLng = (float) ($meta['last_longitude'] ?? $position->longitude);
        $distance = (float) ($meta['distance_meters'] ?? 0)
            + GeoMath::haversineMeters($lastLat, $lastLng, (float) $position->latitude, (float) $position->longitude);

        $this->states->touch($state, $position, [
            'max_speed_kmh' => max((float) ($meta['max_speed_kmh'] ?? 0), $speedKmh),
            'speed_sum' => (float) ($meta['speed_sum'] ?? 0) + $speedKmh,
            'position_count' => (int) ($meta['position_count'] ?? 0) + 1,
            'distance_meters' => round($distance, 1),
            'last_latitude' => $position->latitude,
            'last_longitude' => $position->longitude,
        ]);
    }

    private function finalizeEpisode(
        AlertState $state,
        Vehicle $vehicle,
        GpsPosition $position,
        SpeedExcessSettings $settings,
        ?AlertConfig $config,
        CarbonImmutable $endedAt,
    ): ?Alert {
        $meta = is_array($state->meta) ? $state->meta : [];
        $startedAt = CarbonImmutable::parse($state->started_at ?? $position->recorded_at);
        $durationSeconds = max(0, $startedAt->diffInSeconds($endedAt));

        if ($durationSeconds < $settings->minDurationSeconds) {
            return null;
        }

        if ($config === null) {
            return null;
        }

        $positionCount = max(1, (int) ($meta['position_count'] ?? 1));
        $maxSpeedKmh = (float) ($meta['max_speed_kmh'] ?? 0);
        $avgSpeedKmh = round((float) ($meta['speed_sum'] ?? 0) / $positionCount, 1);
        $distanceMeters = (float) ($meta['distance_meters'] ?? 0);

        $eventMeta = [
            'started_at' => $startedAt->toIso8601String(),
            'ended_at' => $endedAt->toIso8601String(),
            'duration_seconds' => $durationSeconds,
            'limit_kmh' => $settings->limitKmh,
            'hysteresis_percent' => $settings->hysteresisPercent,
            'max_speed_kmh' => $maxSpeedKmh,
            'avg_speed_kmh' => $avgSpeedKmh,
            'distance_meters' => $distanceMeters,
            'position_count' => $positionCount,
            'start_latitude' => (float) ($meta['start_latitude'] ?? $position->latitude),
            'start_longitude' => (float) ($meta['start_longitude'] ?? $position->longitude),
            'end_latitude' => (float) $position->latitude,
            'end_longitude' => (float) $position->longitude,
            'start_gps_position_id' => $meta['start_gps_position_id'] ?? null,
            'end_gps_position_id' => $position->getKey(),
            'alert_config_id' => $config->uuid,
        ];

        return $this->dispatcher->dispatch($config, AlertType::SPEED, $vehicle, [
            'gps_position_id' => $meta['start_gps_position_id'] ?? $position->getKey(),
            'equipment_id' => $position->equipment_id,
            'title' => 'Excesso de Velocidade',
            'description' => 'Veículo excedeu o limite configurado de velocidade.',
            'latitude' => $eventMeta['start_latitude'],
            'longitude' => $eventMeta['start_longitude'],
            'speed' => $maxSpeedKmh / 1.852,
            'occurred_at' => $startedAt,
            'meta' => $eventMeta,
        ], createWhenDisabled: true);
    }
}
