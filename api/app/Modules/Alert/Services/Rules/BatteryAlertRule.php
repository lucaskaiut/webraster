<?php

namespace App\Modules\Alert\Services\Rules;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Alert\Services\AlertDispatcher;
use App\Modules\Alert\Services\AlertStateStore;
use App\Modules\Alert\Support\TraccarAttributeReader;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Collection;

class BatteryAlertRule implements AlertRule
{
    public function __construct(
        private readonly AlertConfigService $configs,
        private readonly AlertStateStore $states,
        private readonly AlertDispatcher $dispatcher,
    ) {}

    public function evaluate(Vehicle $vehicle, GpsPosition $position): Collection
    {
        $events = collect();
        $percent = TraccarAttributeReader::batteryPercent(
            $position->battery,
            is_array($position->attributes) ? $position->attributes : null,
        );

        if ($percent === null) {
            return $events;
        }

        foreach ($this->configs->matchingForVehicle($vehicle, AlertType::BATTERY) as $config) {
            $threshold = (float) ($config->settingsWithDefaults()['battery_threshold'] ?? 20);
            $state = $this->states->get(
                (int) $vehicle->tenant_id,
                (int) $vehicle->getKey(),
                AlertType::BATTERY,
                (int) $config->getKey(),
            );
            $low = $percent <= $threshold;

            if ($low && ! $state->is_active) {
                $alert = $this->dispatcher->dispatch($config, AlertType::BATTERY, $vehicle, [
                    'gps_position_id' => $position->getKey(),
                    'equipment_id' => $position->equipment_id,
                    'title' => 'Bateria baixa',
                    'description' => sprintf(
                        'Veículo %s com bateria em %.0f%% (limite %.0f%%).',
                        $vehicle->plate,
                        $percent,
                        $threshold,
                    ),
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'occurred_at' => $position->recorded_at,
                    'meta' => [
                        'battery_percent' => $percent,
                        'threshold' => $threshold,
                        'alert_config_id' => $config->uuid,
                    ],
                ]);

                if ($alert) {
                    $events->push($alert);
                }

                $this->states->activate($state, $position, [
                    'battery_percent' => $percent,
                    'threshold' => $threshold,
                ]);
            } elseif (! $low && $state->is_active) {
                $this->states->deactivate($state, $position);
            } else {
                $this->states->touch($state, $position, [
                    'battery_percent' => $percent,
                    'threshold' => $threshold,
                ]);
            }
        }

        return $events;
    }
}
