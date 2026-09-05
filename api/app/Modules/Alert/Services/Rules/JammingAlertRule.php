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

/**
 * Só gera alerta quando o protocolo informa jamming explicitamente.
 */
class JammingAlertRule implements AlertRule
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
        $isJamming = TraccarAttributeReader::isJamming($attributes);
        $state = $this->states->get((int) $vehicle->tenant_id, (int) $vehicle->getKey(), AlertType::JAMMING);

        if ($isJamming && ! $state->is_active) {
            foreach ($this->configs->matchingForVehicle($vehicle, AlertType::JAMMING) as $config) {
                $alert = $this->dispatcher->dispatch($config, AlertType::JAMMING, $vehicle, [
                    'gps_position_id' => $position->getKey(),
                    'equipment_id' => $position->equipment_id,
                    'title' => 'Jamming detectado',
                    'description' => sprintf('Possível bloqueio de sinal no veículo %s.', $vehicle->plate),
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'occurred_at' => $position->recorded_at,
                    'meta' => ['source' => 'protocol_attribute', 'alert_config_id' => $config->uuid],
                ]);

                if ($alert) {
                    $events->push($alert);
                }
            }

            $this->states->activate($state, $position, ['jamming' => true]);
        } elseif (! $isJamming && $state->is_active) {
            $this->states->deactivate($state, $position);
        }

        return $events;
    }
}
