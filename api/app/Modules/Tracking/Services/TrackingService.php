<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Geofence\Services\GeofenceDetectionService;
use App\Modules\Alert\Services\AlertEngine;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\DTOs\TraccarPosition;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    public function __construct(
        private readonly TraccarGateway $traccar,
        private readonly GeofenceDetectionService $geofenceDetection,
        private readonly AlertEngine $alertEngine,
    ) {}

    /**
     * Lista veículos monitoráveis no escopo atual (tenant + cliente).
     *
     * @return Collection<int, Vehicle>
     */
    public function listMonitoredVehicles(?string $search = null): Collection
    {
        return Vehicle::query()
            ->with(['client', 'equipment'])
            ->where('is_active', true)
            ->whereHas('equipment', fn ($query) => $query->where('is_active', true))
            ->when(filled($search), function ($query) use ($search): void {
                $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $search));

                $query->where(function ($query) use ($search, $normalized): void {
                    $query->where('plate', 'like', "%{$normalized}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('equipment', fn ($equipment) => $equipment->where('imei', 'like', "%{$search}%"));
                });
            })
            ->orderBy('plate')
            ->limit(200)
            ->get();
    }

    /**
     * Posições ao vivo: consulta Traccar e persiste/atualiza última posição local.
     *
     * @return Collection<int, array{vehicle: Vehicle, position: ?GpsPosition, online: bool}>
     */
    public function live(?string $search = null): Collection
    {
        $vehicles = $this->listMonitoredVehicles($search);

        if ($vehicles->isEmpty()) {
            return collect();
        }

        $deviceMap = $this->resolveTraccarDevices($vehicles);
        $deviceIds = array_values(array_filter($deviceMap));

        $positionsByDevice = collect();

        if ($deviceIds !== [] && $this->traccar->isConfigured()) {
            try {
                $positionsByDevice = $this->traccar->latestPositions($deviceIds)
                    ->keyBy(fn (TraccarPosition $position) => $position->deviceId);
            } catch (\Throwable $exception) {
                Log::warning('tracking.live_failed', ['message' => $exception->getMessage()]);
            }
        }

        return $vehicles->map(function (Vehicle $vehicle) use ($deviceMap, $positionsByDevice) {
            $equipment = $vehicle->equipment;
            $deviceId = $equipment ? ($deviceMap[$equipment->getKey()] ?? null) : null;
            $traccarPosition = $deviceId !== null ? $positionsByDevice->get($deviceId) : null;

            $position = null;

            if ($traccarPosition instanceof TraccarPosition && $equipment !== null) {
                $position = $this->persistPosition($vehicle, $equipment, $traccarPosition);
            } else {
                $position = GpsPosition::query()
                    ->where('vehicle_id', $vehicle->getKey())
                    ->orderByDesc('recorded_at')
                    ->first();
            }

            $online = $traccarPosition !== null
                && $traccarPosition->recordedAt->greaterThan(CarbonImmutable::now()->subMinutes(15));

            return [
                'vehicle' => $vehicle,
                'position' => $position,
                'online' => $online,
            ];
        })->values();
    }

    /**
     * @return Collection<int, GpsPosition>
     */
    public function history(Vehicle $vehicle, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $equipment = $vehicle->equipment;

        if ($equipment === null) {
            throw ValidationException::withMessages([
                'vehicle' => ['Este veículo não possui equipamento instalado.'],
            ]);
        }

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'from' => ['A data inicial deve ser anterior à data final.'],
            ]);
        }

        if ($from->diffInDays($to) > 31) {
            throw ValidationException::withMessages([
                'to' => ['O intervalo máximo do histórico é de 31 dias.'],
            ]);
        }

        $deviceId = $this->resolveDeviceId($equipment);

        if ($deviceId !== null && $this->traccar->isConfigured()) {
            try {
                $remote = $this->traccar->positionHistory($deviceId, $from, $to)
                    ->sortBy(fn (TraccarPosition $position) => $position->recordedAt->timestamp)
                    ->values();

                foreach ($remote as $traccarPosition) {
                    $this->persistPosition($vehicle, $equipment, $traccarPosition);
                }
            } catch (\Throwable $exception) {
                Log::warning('tracking.history_failed', ['message' => $exception->getMessage()]);
            }
        }

        return GpsPosition::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->limit(5000)
            ->get();
    }

    public function gatewayStatus(): array
    {
        return [
            'enabled' => (bool) config('traccar.enabled'),
            'configured' => $this->traccar->isConfigured(),
            'base_url' => config('traccar.enabled') ? config('traccar.base_url') : null,
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, int|null> equipmentId => traccarDeviceId
     */
    private function resolveTraccarDevices(Collection $vehicles): array
    {
        $map = [];

        foreach ($vehicles as $vehicle) {
            $equipment = $vehicle->equipment;

            if ($equipment === null) {
                continue;
            }

            $map[$equipment->getKey()] = $this->resolveDeviceId($equipment);
        }

        return $map;
    }

    private function resolveDeviceId(Equipment $equipment): ?int
    {
        if ($equipment->traccar_device_id) {
            return (int) $equipment->traccar_device_id;
        }

        if (! $this->traccar->isConfigured()) {
            return null;
        }

        try {
            $device = $this->traccar->findDeviceByUniqueId($equipment->imei);
        } catch (\Throwable $exception) {
            Log::warning('tracking.device_lookup_failed', [
                'imei' => $equipment->imei,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($device === null) {
            return null;
        }

        $equipment->forceFill(['traccar_device_id' => $device->id])->save();

        return $device->id;
    }

    private function persistPosition(Vehicle $vehicle, Equipment $equipment, TraccarPosition $position): GpsPosition
    {
        $gpsPosition = GpsPosition::query()->updateOrCreate(
            [
                'tenant_id' => $vehicle->tenant_id,
                'traccar_position_id' => $position->id,
            ],
            [
                'vehicle_id' => $vehicle->getKey(),
                'client_id' => $vehicle->client_id,
                'equipment_id' => $equipment->getKey(),
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'recorded_at' => $position->recordedAt,
                'speed' => $position->speed,
                'ignition' => $position->ignition,
                'battery' => $position->battery,
                'heading' => $position->heading,
                'altitude' => $position->altitude,
                'attributes' => $position->attributes,
            ],
        );

        try {
            $this->geofenceDetection->process($gpsPosition);
        } catch (\Throwable $exception) {
            Log::warning('geofence.detection_failed', [
                'position_id' => $gpsPosition->getKey(),
                'vehicle_id' => $vehicle->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            $this->alertEngine->process($gpsPosition);
        } catch (\Throwable $exception) {
            Log::warning('alert.detection_failed', [
                'position_id' => $gpsPosition->getKey(),
                'vehicle_id' => $vehicle->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }

        return $gpsPosition;
    }
}
