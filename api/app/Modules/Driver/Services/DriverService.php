<?php

namespace App\Modules\Driver\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class DriverService
{
    public function paginate(int $perPage = 15, ?string $search = null, ?int $clientId = null): LengthAwarePaginator
    {
        return Driver::query()
            ->with(['client', 'vehicle'])
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%")
                        ->orWhere('cnh_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Driver
    {
        $payload = $this->payload($data);
        $payload['vehicle_id'] = $this->resolveVehicleId(
            $payload['vehicle_id'] ?? null,
            (int) $payload['client_id'],
        );

        if ($payload['vehicle_id'] !== null) {
            $this->clearVehicleFromOtherDrivers((int) $payload['vehicle_id']);
        }

        return Driver::query()->create($payload)->load(['client', 'vehicle']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Driver $driver, array $data): Driver
    {
        $payload = $this->payload($data);
        $clientId = (int) ($payload['client_id'] ?? $driver->client_id);

        if (array_key_exists('vehicle_id', $payload)) {
            $payload['vehicle_id'] = $this->resolveVehicleId($payload['vehicle_id'], $clientId);

            if ($payload['vehicle_id'] !== null) {
                $this->clearVehicleFromOtherDrivers((int) $payload['vehicle_id'], $driver->getKey());
            }
        }

        $driver->fill($payload);
        $driver->save();

        return $driver->refresh()->load(['client', 'vehicle']);
    }

    public function delete(Driver $driver): void
    {
        $driver->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return Arr::only($data, [
            'client_id',
            'vehicle_id',
            'name',
            'document',
            'phone',
            'email',
            'cnh_number',
            'cnh_expires_at',
            'notes',
            'is_active',
        ]);
    }

    private function resolveVehicleId(mixed $vehicleId, int $clientId): ?int
    {
        if ($vehicleId === null || $vehicleId === '') {
            return null;
        }

        $vehicle = Vehicle::query()->find((int) $vehicleId);

        if ($vehicle === null) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Veículo não encontrado.'],
            ]);
        }

        if ((int) $vehicle->client_id !== $clientId) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['O veículo não pertence ao cliente informado.'],
            ]);
        }

        return $vehicle->getKey();
    }

    private function clearVehicleFromOtherDrivers(int $vehicleId, ?int $exceptDriverId = null): void
    {
        Driver::query()
            ->where('vehicle_id', $vehicleId)
            ->when($exceptDriverId !== null, fn ($query) => $query->where('id', '!=', $exceptDriverId))
            ->update(['vehicle_id' => null]);
    }
}
