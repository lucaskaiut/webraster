<?php

namespace App\Modules\Alert\Services\Rules;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Alert\Services\AlertDispatcher;
use App\Modules\Alert\Services\AlertStateStore;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Collection;

class IgnitionAlertRule implements AlertRule
{
    public function __construct(
        private readonly AlertConfigService $configs,
        private readonly AlertStateStore $states,
        private readonly AlertDispatcher $dispatcher,
    ) {}

    public function evaluate(Vehicle $vehicle, GpsPosition $position): Collection
    {
        $events = collect();

        if ($position->ignition === null) {
            return $events;
        }

        $current = (bool) $position->ignition;
        $state = $this->states->get((int) $vehicle->tenant_id, (int) $vehicle->getKey(), AlertType::IGNITION_ON);
        $previousKnown = $state->meta['ignition'] ?? null;

        if ($previousKnown === null) {
            $this->states->touch($state, $position, ['ignition' => $current]);
            $state->forceFill(['is_active' => $current])->save();

            return $events;
        }

        $previous = (bool) $previousKnown;

        if ($previous === $current) {
            $this->states->touch($state, $position, ['ignition' => $current]);

            return $events;
        }

        if (! $previous && $current) {
            foreach ($this->configs->matchingForVehicle($vehicle, AlertType::IGNITION_ON) as $config) {
                $alert = $this->dispatcher->dispatch($config, AlertType::IGNITION_ON, $vehicle, [
                    'gps_position_id' => $position->getKey(),
                    'equipment_id' => $position->equipment_id,
                    'title' => 'Ignição ligada',
                    'description' => sprintf('Veículo %s ligou a ignição.', $vehicle->plate),
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'occurred_at' => $position->recorded_at,
                    'meta' => ['from' => false, 'to' => true, 'alert_config_id' => $config->uuid],
                ]);
                if ($alert) {
                    $events->push($alert);
                }
            }
        }

        if ($previous && ! $current) {
            foreach ($this->configs->matchingForVehicle($vehicle, AlertType::IGNITION_OFF) as $config) {
                $alert = $this->dispatcher->dispatch($config, AlertType::IGNITION_OFF, $vehicle, [
                    'gps_position_id' => $position->getKey(),
                    'equipment_id' => $position->equipment_id,
                    'title' => 'Ignição desligada',
                    'description' => sprintf('Veículo %s desligou a ignição.', $vehicle->plate),
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'occurred_at' => $position->recorded_at,
                    'meta' => ['from' => true, 'to' => false, 'alert_config_id' => $config->uuid],
                ]);
                if ($alert) {
                    $events->push($alert);
                }
            }
        }

        $this->states->touch($state, $position, ['ignition' => $current]);
        $state->forceFill(['is_active' => $current])->save();

        return $events;
    }
}
