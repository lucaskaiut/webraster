<?php

namespace App\Modules\Geofence\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Models\GeofenceEvent;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GeofenceEventService
{
    public function paginate(
        int $perPage = 15,
        ?int $vehicleId = null,
        ?int $geofenceId = null,
        ?int $clientId = null,
        ?string $type = null,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): LengthAwarePaginator {
        return GeofenceEvent::query()
            ->with(['vehicle', 'geofence.client', 'client'])
            ->when($vehicleId !== null, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when($geofenceId !== null, fn ($query) => $query->where('geofence_id', $geofenceId))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when($from !== null, fn ($query) => $query->where('recorded_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('recorded_at', '<=', $to))
            ->orderByDesc('recorded_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return list<string>
     */
    public function resolveVehicleGeofences(Vehicle $vehicle): array
    {
        return \App\Modules\Geofence\Models\VehicleGeofenceState::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('is_inside', true)
            ->with('geofence')
            ->get()
            ->pluck('geofence.name')
            ->filter()
            ->values()
            ->all();
    }

    public function resolveClientId(?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return Client::query()->where('uuid', $uuid)->value('id');
    }

    public function resolveVehicleId(?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return Vehicle::query()->where('uuid', $uuid)->value('id');
    }

    public function resolveGeofenceId(?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return Geofence::query()->where('uuid', $uuid)->value('id');
    }
}
