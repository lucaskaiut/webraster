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
 * Bateria violada: rastreador desconectado da bateria do veículo
 * (powerCut), violado (tampering) ou removido (removing).
 */
class BatteryTamperAlertRule implements AlertRule
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
        $alarms = TraccarAttributeReader::extractBatteryTamperAlarms($attributes);
        $state = $this->states->get((int) $vehicle->tenant_id, (int) $vehicle->getKey(), AlertType::BATTERY_TAMPER);

        if ($alarms !== [] && ! $state->is_active) {
            $code = $alarms[0];
            $label = TraccarAttributeReader::deviceAlarmLabel($code);

            // Como os alarmes do rastreador, o veículo sem configuração ganha a
            // padrão na primeira ocorrência (alarme crítico: começa ligado).
            if ($this->configs->matchingForVehicle($vehicle, AlertType::BATTERY_TAMPER, false)->isEmpty()) {
                $this->configs->ensureVehicleDefaults($vehicle);
            }

            foreach ($this->configs->matchingForVehicle($vehicle, AlertType::BATTERY_TAMPER) as $config) {
                $alert = $this->dispatcher->dispatch($config, AlertType::BATTERY_TAMPER, $vehicle, [
                    'gps_position_id' => $position->getKey(),
                    'equipment_id' => $position->equipment_id,
                    'title' => 'Bateria violada',
                    'description' => sprintf('%s no veículo %s.', $label, $vehicle->plate),
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'occurred_at' => $position->recorded_at,
                    'meta' => [
                        'alarm_code' => $code,
                        'alarm_codes' => $alarms,
                        'attributes' => array_intersect_key($attributes, array_flip(['alarm', 'event'])),
                        'alert_config_id' => $config->uuid,
                    ],
                ]);

                if ($alert) {
                    $events->push($alert);
                }
            }

            $this->states->activate($state, $position, ['alarm_codes' => $alarms]);
        } elseif ($alarms !== []) {
            $this->states->touch($state, $position, ['alarm_codes' => $alarms]);
        } elseif ($state->is_active) {
            $this->states->deactivate($state, $position);
        }

        return $events;
    }
}
