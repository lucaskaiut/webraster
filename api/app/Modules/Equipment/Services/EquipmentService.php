<?php

namespace App\Modules\Equipment\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Support\QuerySort;
use App\Modules\Tracking\Services\TraccarDeviceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentService
{
    public function __construct(private readonly TraccarDeviceService $traccarDevices) {}

    public function paginate(
        int|string $perPage = 15,
        ?string $search = null,
        ?bool $availableOnly = null,
        ?int $vehicleId = null,
        ?bool $isActive = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        // Compatibilidade com o antigo seletor de ordenação por data.
        $sort = match ($sort) {
            'oldest' => 'created_at:asc',
            'newest' => 'created_at:desc',
            default => $sort,
        };

        $query = Equipment::query()
            ->with('vehicle.client')
            ->when($availableOnly === true, fn ($query) => $query->whereNull('vehicle_id'))
            ->when($availableOnly === false, fn ($query) => $query->whereNotNull('vehicle_id'))
            ->when($vehicleId !== null, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('imei', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('line_number', 'like', "%{$search}%")
                        ->orWhere('carrier', 'like', "%{$search}%");
                });
            });

        QuerySort::apply(
            $query,
            $sort,
            ['imei', 'model', 'line_number', 'carrier', 'is_active', 'traccar_last_update', 'created_at'],
            'created_at:desc',
        );

        if ($perPage === 'all') {
            $total = (clone $query)->toBase()->getCountForPagination();

            return $query->paginate(max(1, $total));
        }

        return $query->paginate(min(max((int) $perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Equipment
    {
        return DB::transaction(function () use ($data): Equipment {
            $equipment = Equipment::query()->create($this->payload($data));
            $this->traccarDevices->sync($equipment);

            return $equipment->load('vehicle.client');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Equipment $equipment, array $data): Equipment
    {
        return DB::transaction(function () use ($equipment, $data): Equipment {
            $identityChanged = $this->identityChanged($equipment, $data);
            $equipment->fill($this->payload($data));
            $equipment->save();
            $this->traccarDevices->sync($equipment, $identityChanged || blank($equipment->traccar_device_id));

            return $equipment->refresh()->load('vehicle.client');
        });
    }

    public function delete(Equipment $equipment): void
    {
        if ($equipment->isAssigned()) {
            throw ValidationException::withMessages([
                'equipment' => ['Remova o equipamento do veículo antes de excluí-lo.'],
            ]);
        }

        $equipment->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return Arr::only($data, [
            'imei',
            'model',
            'line_number',
            'carrier',
            'is_active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function identityChanged(Equipment $equipment, array $data): bool
    {
        if (array_key_exists('imei', $data) && (string) $data['imei'] !== (string) $equipment->imei) {
            return true;
        }

        if (array_key_exists('model', $data) && (string) ($data['model'] ?? '') !== (string) ($equipment->model ?? '')) {
            return true;
        }

        return false;
    }
}
