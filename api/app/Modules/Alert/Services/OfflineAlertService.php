<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Offline usa job agendado (não polling pesado por request).
 * ONLINE é gerado quando uma nova posição chega após estado offline ativo.
 */
class OfflineAlertService
{
    public function __construct(
        private readonly AlertConfigService $configs,
        private readonly AlertStateStore $states,
        private readonly AlertDispatcher $dispatcher,
    ) {}

    /**
     * @return Collection<int, Alert>
     */
    public function scan(): Collection
    {
        $alerts = collect();
        $now = CarbonImmutable::now();

        $equipments = Equipment::query()
            ->withoutGlobalScopes()
            ->whereNotNull('vehicle_id')
            ->where('is_active', true)
            ->with(['vehicle' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();

        foreach ($equipments as $equipment) {
            $vehicle = $equipment->vehicle;

            if ($vehicle === null || ! $vehicle->is_active) {
                continue;
            }

            try {
                $alerts = $alerts->merge($this->evaluateVehicle($vehicle, $now));
            } catch (\Throwable $exception) {
                Log::warning('alert.offline_scan_failed', [
                    'vehicle_id' => $vehicle->getKey(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $alerts;
    }

    /**
     * @return Collection<int, Alert>
     */
    public function evaluateVehicle(Vehicle $vehicle, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $alerts = collect();

        $lastPosition = GpsPosition::query()
            ->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->orderByDesc('recorded_at')
            ->first();

        if ($lastPosition === null) {
            return $alerts;
        }

        $lastAt = CarbonImmutable::parse($lastPosition->recorded_at);

        foreach ($this->configs->matchingForVehicle($vehicle, AlertType::OFFLINE) as $config) {
            $minutes = (int) ($config->settingsWithDefaults()['offline_minutes'] ?? 15);
            $state = $this->states->get(
                (int) $vehicle->tenant_id,
                (int) $vehicle->getKey(),
                AlertType::OFFLINE,
                (int) $config->getKey(),
            );

            $isOffline = $lastAt->addMinutes($minutes)->lessThanOrEqualTo($now);

            if ($isOffline && ! $state->is_active) {
                $alert = $this->dispatcher->dispatch($config, AlertType::OFFLINE, $vehicle, [
                    'gps_position_id' => $lastPosition->getKey(),
                    'equipment_id' => $lastPosition->equipment_id,
                    'title' => 'Dispositivo offline',
                    'description' => sprintf(
                        'Veículo %s sem comunicação há mais de %d minutos (última posição %s).',
                        $vehicle->plate,
                        $minutes,
                        $lastAt->toIso8601String(),
                    ),
                    'latitude' => $lastPosition->latitude,
                    'longitude' => $lastPosition->longitude,
                    'occurred_at' => $now,
                    'meta' => [
                        'offline_minutes' => $minutes,
                        'last_recorded_at' => $lastAt->toIso8601String(),
                        'alert_config_id' => $config->uuid,
                    ],
                ]);

                $this->states->activate($state, $lastPosition, [
                    'offline_minutes' => $minutes,
                ], $now);

                if ($alert) {
                    $alerts->push($alert);
                }
            }
        }

        return $alerts;
    }

    /**
     * @return Collection<int, Alert>
     */
    public function markOnline(Vehicle $vehicle, GpsPosition $position): Collection
    {
        $alerts = collect();
        $activeStates = $this->states->activeForVehicle((int) $vehicle->getKey(), AlertType::OFFLINE);

        if ($activeStates->isEmpty()) {
            return $alerts;
        }

        foreach ($activeStates as $state) {
            // Posição anterior (ou igual) ao início do offline é reprocessamento
            // e não caracteriza reconexão do dispositivo.
            if ($state->started_at !== null
                && CarbonImmutable::parse($position->recorded_at)->lessThanOrEqualTo($state->started_at)) {
                continue;
            }

            $config = null;

            if ((int) $state->alert_config_id > 0) {
                $config = AlertConfig::query()
                    ->withoutGlobalScopes()
                    ->find($state->alert_config_id);
            }

            if ($config === null) {
                $config = $this->configs->matchingForVehicle($vehicle, AlertType::OFFLINE, false)->first();
            }

            if ($config === null) {
                $this->states->deactivate($state, $position);

                continue;
            }

            $alert = $this->dispatcher->dispatch($config, AlertType::ONLINE, $vehicle, [
                'gps_position_id' => $position->getKey(),
                'equipment_id' => $position->equipment_id,
                'title' => 'Dispositivo online',
                'description' => sprintf('Veículo %s voltou a comunicar.', $vehicle->plate),
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'occurred_at' => $position->recorded_at,
                'meta' => ['recovered' => true, 'alert_config_id' => $config->uuid],
            ]);

            $this->states->deactivate($state, $position);

            if ($alert) {
                $alerts->push($alert);
            }
        }

        return $alerts;
    }
}
