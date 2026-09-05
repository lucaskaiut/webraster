<?php

namespace App\Modules\Equipment\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Services\TraccarDeviceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentService
{
    public function __construct(private readonly TraccarDeviceService $traccarDevices) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?bool $availableOnly = null,
        ?int $vehicleId = null,
    ): LengthAwarePaginator {
        return Equipment::query()
            ->with('vehicle.client')
            ->when($availableOnly === true, fn ($query) => $query->whereNull('vehicle_id'))
            ->when($vehicleId !== null, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('imei', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('iccid', 'like', "%{$search}%")
                        ->orWhere('carrier', 'like', "%{$search}%");
                });
            })
            ->orderBy('imei')
            ->paginate(min(max($perPage, 1), 100));
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
            'iccid',
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
