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

class SosAlertRule implements AlertRule
{
    public function __construct(
        private readonly AlertConfigService $configs,
        private readonly AlertStateStore $states,
        private readonly AlertDispatcher $dispatcher,
    ) {}

    public function evaluate(Vehicle $vehicle, GpsPosition $position): Collection
    {
        $events = collect();
        $attributes = is_array($position->attributes) ? $position->attributes : [];
        $isSos = TraccarAttributeReader::isSos($attributes);
        $state = $this->states->get((int) $vehicle->tenant_id, (int) $vehicle->getKey(), AlertType::SOS);

        if ($isSos && ! $state->is_active) {
            foreach ($this->configs->matchingForVehicle($vehicle, AlertType::SOS) as $config) {
                $alert = $this->dispatcher->dispatch($config, AlertType::SOS, $vehicle, [
                    'gps_position_id' => $position->getKey(),
                    'equipment_id' => $position->equipment_id,
                    'title' => 'SOS acionado',
                    'description' => sprintf('Botão SOS do veículo %s foi acionado.', $vehicle->plate),
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'occurred_at' => $position->recorded_at,
                    'meta' => [
                        'attributes' => array_intersect_key($attributes, array_flip(['sos', 'alarm'])),
                        'alert_config_id' => $config->uuid,
                    ],
                ]);

                if ($alert) {
                    $events->push($alert);
                }
            }

            $this->states->activate($state, $position, ['sos' => true]);
        } elseif (! $isSos && $state->is_active) {
            $this->states->deactivate($state, $position);
        } else {
            $this->states->touch($state, $position, ['sos' => $isSos]);
        }

        return $events;
    }
}
