<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Alert\Services\AlertEngine;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Geofence\Services\GeofenceDetectionService;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\DTOs\TraccarPosition;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
     * Posições ao vivo: lê a última posição persistida (alimentada pelo webhook
     * do Traccar) em vez de consultar o Traccar diretamente a cada polling.
     *
     * @return Collection<int, array{vehicle: Vehicle, position: ?GpsPosition, online: bool}>
     */
    public function live(?string $search = null): Collection
    {
        $vehicles = $this->listMonitoredVehicles($search);

        if ($vehicles->isEmpty()) {
            return collect();
        }

        // Vincula equipamentos sem traccar_device_id (necessário para o webhook
        // conseguir associar as posições que chegam do Traccar).
        foreach ($vehicles as $vehicle) {
            $equipment = $vehicle->equipment;

            if ($equipment !== null && blank($equipment->traccar_device_id)) {
                $this->resolveDeviceId($equipment);
            }
        }

        $positions = $this->latestPositionsFor($vehicles->pluck('id')->all());
        $now = CarbonImmutable::now();

        return $vehicles->map(function (Vehicle $vehicle) use ($positions, $now) {
            $position = $positions->get($vehicle->getKey());

            $online = $position !== null
                && $position->recorded_at !== null
                && $position->recorded_at->greaterThan($now->subMinutes(15));

            return [
                'vehicle' => $vehicle,
                'position' => $position,
                'online' => $online,
            ];
        })->values();
    }

    /**
     * O webhook e o SyncTraccarPositionsJob já persistem as posições em tempo
     * real, então o banco é a fonte primária do histórico. O Traccar só é
     * consultado quando o período não possui nenhuma posição local — evitando
     * baixar e reprocessar milhares de posições a cada consulta.
     *
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

        $positions = $this->persistedHistory($vehicle, $from, $to);

        if ($positions->isNotEmpty()) {
            return $positions;
        }

        $deviceId = $this->resolveDeviceId($equipment);

        if ($deviceId === null || ! $this->traccar->isConfigured()) {
            return $positions;
        }

        try {
            $this->backfillHistory($vehicle, $equipment, $deviceId, $from, $to);
        } catch (\Throwable $exception) {
            Log::warning('tracking.history_failed', ['message' => $exception->getMessage()]);
        }

        return $this->persistedHistory($vehicle, $from, $to);
    }

    /**
     * @return Collection<int, GpsPosition>
     */
    private function persistedHistory(Vehicle $vehicle, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return GpsPosition::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->limit(5000)
            ->get();
    }

    /**
     * Importa o histórico remoto em lote (uma query por chunk de 500 posições).
     *
     * Geocercas e alertas não são reprocessados: o pipeline em tempo real
     * (webhook + sync) é quem gera esses eventos; o replay de um período
     * inteiro criaria alertas retroativos e uma transação por posição.
     */
    private function backfillHistory(
        Vehicle $vehicle,
        Equipment $equipment,
        int $deviceId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): void {
        $rows = $this->traccar->positionHistory($deviceId, $from, $to)
            ->sortBy(fn (TraccarPosition $position) => $position->recordedAt->timestamp)
            ->map(fn (TraccarPosition $position) => $this->positionRow($vehicle, $equipment, $position))
            ->keyBy('recorded_at')
            ->values();

        $updateColumns = [
            'tenant_id',
            'vehicle_id',
            'client_id',
            'traccar_position_id',
            'latitude',
            'longitude',
            'server_time',
            'speed',
            'ignition',
            'battery',
            'heading',
            'altitude',
            'address',
            'valid',
            'attributes',
        ];

        foreach ($rows->chunk(500) as $chunk) {
            GpsPosition::query()->upsert(
                $chunk->values()->all(),
                ['equipment_id', 'recorded_at'],
                $updateColumns,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function positionRow(Vehicle $vehicle, Equipment $equipment, TraccarPosition $position): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $vehicle->tenant_id,
            'vehicle_id' => $vehicle->getKey(),
            'client_id' => $vehicle->client_id,
            'equipment_id' => $equipment->getKey(),
            'traccar_position_id' => $position->id > 0 ? $position->id : null,
            'latitude' => $position->latitude,
            'longitude' => $position->longitude,
            'recorded_at' => $position->recordedAt->utc()->toDateTimeString(),
            'server_time' => $position->serverTime?->utc()->toDateTimeString(),
            'speed' => $position->speed,
            'ignition' => $position->ignition,
            'battery' => $position->battery,
            'heading' => $position->heading,
            'altitude' => $position->altitude,
            'address' => $position->address,
            'valid' => $position->valid,
            'attributes' => json_encode($position->attributes),
        ];
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
     * Última posição persistida de cada veículo (alimentada pelo webhook).
     * Usa recorded_at, não o maior ID: o backfill pode inserir linhas antigas
     * com IDs maiores que a linha alimentada em tempo real.
     *
     * O máximo por veículo é resolvido em uma consulta agregada (índice
     * vehicle_id + recorded_at) e a carga final usa igualdade por veículo,
     * evitando a subquery correlacionada executada linha a linha.
     *
     * @param  list<int>  $vehicleIds
     * @return Collection<int, GpsPosition>
     */
    private function latestPositionsFor(array $vehicleIds): Collection
    {
        if ($vehicleIds === []) {
            return collect();
        }

        $latestRecordedAt = GpsPosition::query()
            ->select('vehicle_id')
            ->selectRaw('MAX(recorded_at) AS max_recorded_at')
            ->whereIn('vehicle_id', $vehicleIds)
            ->groupBy('vehicle_id')
            ->get();

        if ($latestRecordedAt->isEmpty()) {
            return collect();
        }

        return GpsPosition::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where(function ($query) use ($latestRecordedAt): void {
                foreach ($latestRecordedAt as $latest) {
                    $query->orWhere(function ($query) use ($latest): void {
                        $query->where('vehicle_id', $latest->vehicle_id)
                            ->where('recorded_at', $latest->max_recorded_at);
                    });
                }
            })
            ->orderByDesc('id')
            ->get()
            ->unique('vehicle_id')
            ->keyBy('vehicle_id');
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

    public function persistPosition(Vehicle $vehicle, Equipment $equipment, TraccarPosition $position): GpsPosition
    {
        // O forward do Traccar chega antes do ID do banco ser atribuído (id=0),
        // então a idempotência usa a chave natural equipamento + horário.
        $gpsPosition = GpsPosition::query()->updateOrCreate(
            [
                'equipment_id' => $equipment->getKey(),
                'recorded_at' => $position->recordedAt,
            ],
            [
                'tenant_id' => $vehicle->tenant_id,
                'vehicle_id' => $vehicle->getKey(),
                'client_id' => $vehicle->client_id,
                'traccar_position_id' => $position->id > 0 ? $position->id : null,
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'recorded_at' => $position->recordedAt,
                'server_time' => $position->serverTime,
                'speed' => $position->speed,
                'ignition' => $position->ignition,
                'battery' => $position->battery,
                'heading' => $position->heading,
                'altitude' => $position->altitude,
                'address' => $position->address,
                'valid' => $position->valid,
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
