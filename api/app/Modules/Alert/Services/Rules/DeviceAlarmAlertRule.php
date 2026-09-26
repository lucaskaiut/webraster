<?php

namespace App\Modules\Alert\Services\Rules;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertState;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Alert\Services\AlertDispatcher;
use App\Modules\Alert\Services\AlertStateStore;
use App\Modules\Alert\Support\TraccarAttributeReader;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Alarmes Traccar enviados pelo protocolo (powerCut, vibração, reboque, etc.).
 */
class DeviceAlarmAlertRule implements AlertRule
{
    public function __construct(
        private readonly AlertConfigService $configs,
        private readonly AlertStateStore $states,
        private readonly AlertDispatcher $dispatcher,
    ) {}

    public function evaluate(Vehicle $vehicle, GpsPosition $position): Collection
    {
        $attributes = is_array($position->attributes) ? $position->attributes : [];
        $events = collect();

        foreach (TraccarAttributeReader::extractRestoredAlarms($attributes) as $restored) {
            $base = TraccarAttributeReader::restoredBaseAlarm($restored);

            if ($base !== null) {
                $this->deactivateAlarm($vehicle, $position, $base);
            }
        }

        $alarms = TraccarAttributeReader::extractDeviceAlarms($attributes);

        if ($alarms === []) {
            $this->deactivateAllDeviceAlarms($vehicle, $position);

            return $events;
        }

        foreach ($alarms as $alarm) {
            $scopeId = AlertStateStore::scopeIdForDeviceAlarm($alarm);
            $state = $this->states->get(
                (int) $vehicle->tenant_id,
                (int) $vehicle->getKey(),
                AlertType::DEVICE_ALARM,
                $scopeId,
            );

            if (! $state->is_active) {
                $label = TraccarAttributeReader::deviceAlarmLabel($alarm);
                $severity = TraccarAttributeReader::deviceAlarmSeverity($alarm);
                $config = $this->configs->deviceAlarmConfig($vehicle, $alarm);

                $alert = $this->dispatcher->dispatch(
                    $config,
                    AlertType::DEVICE_ALARM,
                    $vehicle,
                    [
                        'gps_position_id' => $position->getKey(),
                        'equipment_id' => $position->equipment_id,
                        'title' => $label,
                        'description' => sprintf(
                            'Alarme "%s" no veículo %s.',
                            $label,
                            $vehicle->plate,
                        ),
                        'latitude' => $position->latitude,
                        'longitude' => $position->longitude,
                        'occurred_at' => $position->recorded_at,
                        'meta' => [
                            'alarm_code' => $alarm,
                            'attributes' => array_intersect_key($attributes, array_flip(['alarm', 'event'])),
                            'alert_config_id' => $config->uuid,
                        ],
                    ],
                    $severity,
                );

                if ($alert) {
                    $events->push($alert);
                }

                $this->states->activate($state, $position, ['alarm_code' => $alarm]);
            } else {
                $this->states->touch($state, $position, ['alarm_code' => $alarm]);
            }
        }

        $this->deactivateAlarmsExcept($vehicle, $position, $alarms);

        return $events;
    }

    private function deactivateAllDeviceAlarms(Vehicle $vehicle, GpsPosition $position): void
    {
        $activeStates = AlertState::query()
            ->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', AlertType::DEVICE_ALARM->value)
            ->where('is_active', true)
            ->get();

        foreach ($activeStates as $state) {
            $this->states->deactivate($state, $position);
        }
    }

    /**
     * @param  list<string>  $activeAlarms
     */
    private function deactivateAlarmsExcept(Vehicle $vehicle, GpsPosition $position, array $activeAlarms): void
    {
        $activeStates = AlertState::query()
            ->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', AlertType::DEVICE_ALARM->value)
            ->where('is_active', true)
            ->get();

        foreach ($activeStates as $state) {
            $code = is_array($state->meta) ? ($state->meta['alarm_code'] ?? null) : null;

            if ($code !== null && ! in_array($code, $activeAlarms, true)) {
                $this->states->deactivate($state, $position);
            }
        }
    }

    private function deactivateAlarm(Vehicle $vehicle, GpsPosition $position, string $alarmCode): void
    {
        $scopeId = AlertStateStore::scopeIdForDeviceAlarm($alarmCode);
        $state = $this->states->get(
            (int) $vehicle->tenant_id,
            (int) $vehicle->getKey(),
            AlertType::DEVICE_ALARM,
            $scopeId,
        );

        if ($state->is_active) {
            $this->states->deactivate($state, $position);
        }
    }
}
