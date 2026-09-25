<?php

namespace App\Modules\Vehicle\Services;

use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class VehicleService
{
    public function paginate(int $perPage = 15, ?string $search = null, ?int $clientId = null): LengthAwarePaginator
    {
        return Vehicle::query()
            ->with(['client', 'equipment', 'images'])
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when(filled($search), function ($query) use ($search): void {
                $normalized = $this->normalizePlate($search);

                $query->where(function ($query) use ($search, $normalized): void {
                    $query->where('plate', 'like', "%{$normalized}%")
                        ->orWhere('chassis', 'like', "%{$search}%")
                        ->orWhere('renavam', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->orderBy('plate')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Vehicle
    {
        return Vehicle::query()->create($this->payload($data))->load(['client', 'equipment']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->fill($this->payload($data));
        $vehicle->save();

        return $vehicle->refresh()->load(['client', 'equipment']);
    }

    public function delete(Vehicle $vehicle): void
    {
        $vehicle->load('equipment');

        if ($vehicle->equipment !== null) {
            app(EquipmentAssignmentService::class)->remove($vehicle, 'Remoção automática ao excluir o veículo.');
        }

        $vehicle->delete();
    }

    public function normalizePlate(string $plate): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $payload = Arr::only($data, [
            'client_id',
            'plate',
            'chassis',
            'renavam',
            'brand',
            'model',
            'color',
            'year',
            'vehicle_type',
            'transmission',
            'odometer',
            'max_speed_kmh',
            'speed_hysteresis_percent',
            'speed_min_duration_seconds',
            'average_consumption',
            'tank_capacity',
            'crlv_file',
            'fipe_code',
            'fipe_model_year',
            'fipe_fuel',
            'fipe_reference_month',
            'fipe_value',
            'fipe_model',
            'fipe_brand',
            'fipe_score',
            'is_active',
        ]);

        if (array_key_exists('plate', $payload) && filled($payload['plate'])) {
            $payload['plate'] = $this->normalizePlate((string) $payload['plate']);
        }

        if (array_key_exists('chassis', $payload) && filled($payload['chassis'])) {
            $payload['chassis'] = Str::upper((string) $payload['chassis']);
        }

        return $payload;
    }
}
