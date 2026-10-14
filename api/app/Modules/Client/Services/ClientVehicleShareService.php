<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\Client;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleShare;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Associação de veículos de outros clientes (somente leitura para o cliente
 * autorizado). A propriedade do veículo permanece com o cliente de origem.
 */
class ClientVehicleShareService
{
    public function paginate(Client $client, int $perPage = 15): LengthAwarePaginator
    {
        return VehicleShare::query()
            ->with(['vehicle.client', 'vehicle.equipment', 'creator'])
            ->where('client_id', $client->getKey())
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function attach(Client $client, Vehicle $vehicle, ?User $user): VehicleShare
    {
        if ((int) $vehicle->client_id === (int) $client->getKey()) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Este veículo já pertence ao cliente informado.'],
            ]);
        }

        $alreadyShared = VehicleShare::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('client_id', $client->getKey())
            ->exists();

        if ($alreadyShared) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Este veículo já está compartilhado com o cliente informado.'],
            ]);
        }

        return DB::transaction(function () use ($client, $vehicle, $user): VehicleShare {
            $share = VehicleShare::query()->create([
                'tenant_id' => $client->tenant_id,
                'vehicle_id' => $vehicle->getKey(),
                'client_id' => $client->getKey(),
                'created_by' => $user?->getKey(),
            ]);

            return $share->load(['vehicle.client', 'vehicle.equipment', 'creator']);
        });
    }

    public function detach(Client $client, VehicleShare $share): void
    {
        if ((int) $share->client_id !== (int) $client->getKey()) {
            abort(404);
        }

        $share->delete();
    }
}
