<?php

namespace App\Modules\Alert\Services\Rules;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Alert\Services\AlertDispatcher;
use App\Modules\Geofence\Enums\GeofenceEventType;
use App\Modules\Geofence\Models\GeofenceEvent;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Entrada/saída de geocercas detectadas pelo sistema
 * (GeofenceDetectionService), independente do rastreador. O operador
 * habilita o alerta por veículo; o cliente escolhe o som no app.
 */
class GeofenceAlertRule implements AlertRule
{
    public function __construct(
        private readonly AlertConfigService $configs,
        private readonly AlertDispatcher $dispatcher,
    ) {}

    public function evaluate(Vehicle $vehicle, GpsPosition $position): Collection
    {
        $events = GeofenceEvent::query()
            ->withoutGlobalScopes()
            ->with('geofence')
            ->where('gps_position_id', $position->getKey())
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            return collect();
        }

        $config = $this->configs
            ->matchingForVehicle($vehicle, AlertType::GEOFENCE)
            ->first();

        if ($config === null) {
            return collect();
        }

        $alerts = collect();

        foreach ($events as $event) {
            $entry = $event->type === GeofenceEventType::ENTRY;
            $name = $event->geofence?->name ?? 'geocerca';

            $alert = $this->dispatcher->dispatch($config, AlertType::GEOFENCE, $vehicle, [
                'gps_position_id' => $position->getKey(),
                'equipment_id' => $position->equipment_id,
                'title' => $entry
                    ? sprintf('Entrada na geocerca %s', $name)
                    : sprintf('Saída da geocerca %s', $name),
                'description' => $entry
                    ? sprintf('Veículo %s entrou na geocerca %s.', $vehicle->plate, $name)
                    : sprintf('Veículo %s saiu da geocerca %s.', $vehicle->plate, $name),
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'occurred_at' => $position->recorded_at,
                'meta' => [
                    'geofence_id' => $event->geofence?->uuid,
                    'event_type' => $event->type->value,
                    'alert_config_id' => $config->uuid,
                ],
            ]);

            if ($alert !== null) {
                $alerts->push($alert);
            }
        }

        return $alerts;
    }
}
