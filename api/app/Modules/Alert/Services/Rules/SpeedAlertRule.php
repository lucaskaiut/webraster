<?php

namespace App\Modules\Alert\Services\Rules;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Alert\Services\AlertDispatcher;
use App\Modules\Alert\Services\AlertStateStore;
use App\Modules\Alert\Support\SpeedConverter;
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

        $recordedAt = CarbonImmutable::parse($position->recorded_at);
        $configs = $this->configs->matchingForVehicle($vehicle, AlertType::SPEED);

        foreach ($configs as $config) {
            $settings = $config->settingsWithDefaults();
            $limit = (float) ($settings['speed_limit_kmh'] ?? 80);
            $minDuration = (int) ($settings['min_duration_seconds'] ?? 60);
            $state = $this->states->get(
                (int) $vehicle->tenant_id,
                (int) $vehicle->getKey(),
                AlertType::SPEED,
                (int) $config->getKey(),
            );

            $exceeding = $speedKmh > $limit;

            if ($exceeding) {
                if (! $state->is_active) {
                    $this->states->activate($state, $position, [
                        'speed_kmh' => $speedKmh,
                        'limit_kmh' => $limit,
                        'alerted' => false,
                    ], $recordedAt);

                    continue;
                }

                $this->states->touch($state, $position, [
                    'speed_kmh' => $speedKmh,
                    'limit_kmh' => $limit,
                ]);

                $startedAt = CarbonImmutable::parse($state->started_at ?? $position->recorded_at);
                $elapsed = $startedAt->diffInSeconds($recordedAt);
                $alreadyAlerted = (bool) (($state->meta['alerted'] ?? false));

                if ($elapsed >= $minDuration && ! $alreadyAlerted) {
                    $alert = $this->dispatcher->dispatch($config, AlertType::SPEED, $vehicle, [
                        'gps_position_id' => $position->getKey(),
                        'equipment_id' => $position->equipment_id,
                        'title' => 'Excesso de velocidade',
                        'description' => sprintf(
                            'Veículo %s a %.0f km/h (limite %.0f km/h) por %d s.',
                            $vehicle->plate,
                            $speedKmh,
                            $limit,
                            $elapsed,
                        ),
                        'latitude' => $position->latitude,
                        'longitude' => $position->longitude,
                        'speed' => $position->speed,
                        'occurred_at' => $position->recorded_at,
                        'meta' => [
                            'speed_kmh' => $speedKmh,
                            'limit_kmh' => $limit,
                            'duration_seconds' => $elapsed,
                            'alert_config_id' => $config->uuid,
                        ],
                    ]);

                    if ($alert) {
                        $events->push($alert);
                        $this->states->touch($state->fresh() ?? $state, $position, [
                            'speed_kmh' => $speedKmh,
                            'limit_kmh' => $limit,
                            'alerted' => true,
                        ]);
                    }
                }

                continue;
            }

            if ($state->is_active) {
                $this->states->deactivate($state, $position);
            }
        }

        return $events;
    }
}
