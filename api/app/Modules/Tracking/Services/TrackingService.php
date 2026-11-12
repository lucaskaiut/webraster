<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Alert\Services\AlertEngine;
use App\Modules\Alert\Support\TraccarAttributeReader;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Geofence\Services\GeofenceDetectionService;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\DTOs\TraccarPosition;
use App\Modules\Tracking\Events\PositionUpdated;
use App\Modules\Tracking\Http\Resources\GpsPositionResource;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    /**
     * Janela em minutos para considerar a última posição persistida como sinal
     * de que o veículo está online.
     */
    private const POSITION_ONLINE_MINUTES = 15;

    private const PACKET_TIME_KEY = '_packetTime';

    public function __construct(
        private readonly TraccarGateway $traccar,
        private readonly GeofenceDetectionService $geofenceDetection,
        private readonly AlertEngine $alertEngine,
        private readonly EquipmentRawLogService $equipmentRawLogs,
    ) {}

    /**
     * Lista veículos monitoráveis no escopo atual (tenant + cliente).
     *
     * @return Collection<int, Vehicle>
     */
    public function listMonitoredVehicles(?string $search = null): Collection
    {
        return Vehicle::query()
            ->with(['client', 'equipment', 'images', 'driver'])
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
        return $this->liveFor($this->listMonitoredVehicles($search));
    }

    /**
     * Última posição e status de conexão dos veículos informados.
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  bool  $resolveDevices  vincula no Traccar equipamentos sem device id
     * @return Collection<int, array{vehicle: Vehicle, position: ?GpsPosition, online: bool}>
     */
    public function liveFor(Collection $vehicles, bool $resolveDevices = true): Collection
    {
        if ($vehicles->isEmpty()) {
            return collect();
        }

        // Vincula equipamentos sem traccar_device_id (necessário para o webhook
        // conseguir associar as posições que chegam do Traccar).
        foreach ($resolveDevices ? $vehicles : [] as $vehicle) {
            $equipment = $vehicle->equipment;

            if ($equipment !== null && blank($equipment->traccar_device_id)) {
                $this->resolveDeviceId($equipment);
            }
        }

        $positions = $this->latestPositionsFor($vehicles->pluck('id')->all());
        $now = CarbonImmutable::now();

        return $vehicles->map(function (Vehicle $vehicle) use ($positions, $now) {
            $position = $positions->get($vehicle->getKey());

            return [
                'vehicle' => $vehicle,
                'position' => $position,
                'online' => $this->isOnline($vehicle, $position, $now),
            ];
        })->values();
    }

    /**
     * Equipamentos ativos sem veículo (estoque) com a última posição
     * persistida, para exibir posições e indicadores no cadastro.
     *
     * @return Collection<int, array{equipment: Equipment, position: ?GpsPosition, online: bool}>
     */
    public function liveEquipments(?string $search = null): Collection
    {
        // Usuário de cliente não enxerga o estoque do tenant.
        if (ClientAuthorization::isRestricted()) {
            return collect();
        }

        $equipments = Equipment::query()
            ->with('vehicle.client')
            ->where('is_active', true)
            ->whereNull('vehicle_id')
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('imei', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('line_number', 'like', "%{$search}%")
                        ->orWhere('carrier', 'like', "%{$search}%");
                });
            })
            ->orderBy('imei')
            ->limit(200)
            ->get();

        if ($equipments->isEmpty()) {
            return collect();
        }

        $positions = $this->latestEquipmentPositionsFor($equipments->pluck('id')->all());
        $now = CarbonImmutable::now();

        return $equipments->map(function (Equipment $equipment) use ($positions, $now) {
            $position = $positions->get($equipment->getKey());

            return [
                'equipment' => $equipment,
                'position' => $position,
                'online' => $this->isEquipmentOnline($equipment, $position, $now),
            ];
        })->values();
    }

    private function isEquipmentOnline(
        Equipment $equipment,
        ?GpsPosition $position,
        CarbonImmutable $now,
    ): bool {
        return $this->isSignalOnline($position, $equipment->traccar_last_update, $now);
    }

    /**
     * Online quando a última posição é recente ou quando o Traccar reporta
     * comunicação recente do dispositivo (lastUpdate), mesmo sem posição nova.
     */
    private function isOnline(Vehicle $vehicle, ?GpsPosition $position, CarbonImmutable $now): bool
    {
        return $this->isSignalOnline($position, $vehicle->equipment?->traccar_last_update, $now);
    }

    private function isSignalOnline(
        ?GpsPosition $position,
        ?CarbonInterface $lastUpdate,
        CarbonImmutable $now,
    ): bool {
        if ($position !== null
            && $position->recorded_at !== null
            && $position->recorded_at->greaterThan($now->subMinutes(self::POSITION_ONLINE_MINUTES))) {
            return true;
        }

        if ($lastUpdate === null) {
            return false;
        }

        return $lastUpdate->greaterThan(
            $now->subMinutes((int) config('traccar.online_signal_minutes', 10)),
        );
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

    /**
     * Última posição persistida de equipamentos sem veículo, pela chave
     * natural (equipment_id + recorded_at).
     *
     * @param  list<int>  $equipmentIds
     * @return Collection<int, GpsPosition>
     */
    private function latestEquipmentPositionsFor(array $equipmentIds): Collection
    {
        if ($equipmentIds === []) {
            return collect();
        }

        $latestRecordedAt = GpsPosition::query()
            ->select('equipment_id')
            ->selectRaw('MAX(recorded_at) AS max_recorded_at')
            ->whereIn('equipment_id', $equipmentIds)
            ->whereNull('vehicle_id')
            ->groupBy('equipment_id')
            ->get();

        if ($latestRecordedAt->isEmpty()) {
            return collect();
        }

        return GpsPosition::query()
            ->whereIn('equipment_id', $equipmentIds)
            ->whereNull('vehicle_id')
            ->where(function ($query) use ($latestRecordedAt): void {
                foreach ($latestRecordedAt as $latest) {
                    $query->orWhere(function ($query) use ($latest): void {
                        $query->where('equipment_id', $latest->equipment_id)
                            ->where('recorded_at', $latest->max_recorded_at);
                    });
                }
            })
            ->orderByDesc('id')
            ->get()
            ->unique('equipment_id')
            ->keyBy('equipment_id');
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
        return $this->persist($equipment, $position, $vehicle);
    }

    /**
     * Posição de um equipamento ainda sem vínculo com veículo (estoque).
     * Geocercas e alertas dependem do veículo e não são avaliados.
     */
    public function persistEquipmentPosition(Equipment $equipment, TraccarPosition $position): GpsPosition
    {
        return $this->persist($equipment, $position, null);
    }

    private function persist(Equipment $equipment, TraccarPosition $position, ?Vehicle $vehicle): GpsPosition
    {
        $existing = GpsPosition::query()
            ->where('equipment_id', $equipment->getKey())
            ->where('recorded_at', $position->recordedAt)
            ->first();

        // Protocolos como o GT06 enviam pacotes distintos com o mesmo horário
        // (heartbeat + posição, dois alarmes no mesmo segundo). Em vez de
        // sobrescrever ou descartar, mescla o pacote na linha existente.
        // Um pacote mais antigo que o já mesclado (resync) também passa pela
        // mescla, para não reverter o estado mais recente.
        if ($existing !== null
            && ($this->isDistinctPacket($existing, $position) || $this->isOlderThanRow($existing, $position))) {
            return $this->mergeDistinctPacket($existing, $equipment, $position, $vehicle);
        }

        // O forward do Traccar chega antes do ID do banco ser atribuído (id=0),
        // então a idempotência usa a chave natural equipamento + horário.
        $gpsPosition = GpsPosition::query()->updateOrCreate(
            [
                'equipment_id' => $equipment->getKey(),
                'recorded_at' => $position->recordedAt,
            ],
            [
                'tenant_id' => $equipment->tenant_id,
                'vehicle_id' => $vehicle?->getKey(),
                'client_id' => $vehicle?->client_id,
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
                // Reentrega/resync do mesmo pacote não pode apagar o que outro
                // pacote do mesmo horário já mesclou na linha.
                'attributes' => self::withPacketTime(
                    $existing !== null
                        ? self::mergeAttributes($existing->attributes, $position->attributes, preferIncoming: true)
                        : $position->attributes,
                    $position,
                ),
            ],
        );

        $this->equipmentRawLogs->appendFromPositionAttributes(
            $equipment,
            $position->attributes,
            $position->recordedAt,
        );

        // Apenas posições novas alimentam geocercas, alertas e o tempo real.
        // Reprocessar posições já persistidas (reconciliação do sync ou
        // reentrega do webhook) fazia posições antigas de um dispositivo parado
        // dispararem "dispositivo online" a cada varredura.
        if ($gpsPosition->wasRecentlyCreated) {
            $this->refreshEquipmentLastConnection($equipment, $position);

            if ($vehicle !== null) {
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
            }

            $this->broadcastPosition($equipment, $vehicle, $gpsPosition);
        }

        return $gpsPosition;
    }

    /**
     * Pacotes diferentes só são distinguíveis pelo frame bruto (`raw`); sem
     * ele, mantém o comportamento de reentrega do mesmo pacote.
     */
    private function isDistinctPacket(GpsPosition $existing, TraccarPosition $position): bool
    {
        $existingRaw = is_array($existing->attributes) ? ($existing->attributes['raw'] ?? null) : null;
        $incomingRaw = $position->attributes['raw'] ?? null;

        return is_string($existingRaw) && $existingRaw !== ''
            && is_string($incomingRaw) && $incomingRaw !== ''
            && $existingRaw !== $incomingRaw;
    }

    private function isOlderThanRow(GpsPosition $existing, TraccarPosition $position): bool
    {
        $packetTime = is_array($existing->attributes) ? ($existing->attributes[self::PACKET_TIME_KEY] ?? null) : null;

        return is_string($packetTime) && $position->recordedAt->lessThan(CarbonImmutable::parse($packetTime));
    }

    /**
     * Mescla na linha existente o que o novo pacote traz (status, bateria,
     * sinal, resultado de comando) e une os alarmes. Em conflito vale o pacote
     * mais recente pelo horário com milissegundos — a coluna `recorded_at` só
     * guarda segundos, e um heartbeat "bloqueado" seguido de outro
     * "desbloqueado" no mesmo segundo precisa terminar desbloqueado. Os dados
     * de localização da linha existente são mantidos. Reprocessar um pacote
     * mais antigo (resync) não altera nada.
     */
    private function mergeDistinctPacket(
        GpsPosition $existing,
        Equipment $equipment,
        TraccarPosition $position,
        ?Vehicle $vehicle,
    ): GpsPosition {
        $previousAlarms = TraccarAttributeReader::parseAlarmCodes($existing->attributes['alarm'] ?? null);

        $existingPacketTime = $existing->attributes[self::PACKET_TIME_KEY] ?? null;
        $incomingIsNewer = ! is_string($existingPacketTime)
            || $position->recordedAt->greaterThanOrEqualTo(CarbonImmutable::parse($existingPacketTime));

        $attributes = self::mergeAttributes($existing->attributes, $position->attributes, preferIncoming: $incomingIsNewer);

        // A linha mantém o frame da localização gravada primeiro.
        if (isset($existing->attributes['raw'])) {
            $attributes['raw'] = $existing->attributes['raw'];
        }

        $existing->forceFill([
            'attributes' => $incomingIsNewer ? self::withPacketTime($attributes, $position) : $attributes,
            'ignition' => $incomingIsNewer
                ? ($position->ignition ?? $existing->ignition)
                : ($existing->ignition ?? $position->ignition),
            'battery' => $incomingIsNewer
                ? ($position->battery ?? $existing->battery)
                : ($existing->battery ?? $position->battery),
        ]);

        $this->equipmentRawLogs->appendFromPositionAttributes(
            $equipment,
            $position->attributes,
            $position->recordedAt,
        );

        if (! $existing->isDirty()) {
            return $existing;
        }

        $existing->save();

        $newAlarms = array_diff(
            TraccarAttributeReader::parseAlarmCodes($existing->attributes['alarm'] ?? null),
            $previousAlarms,
        );

        if ($newAlarms !== [] && $vehicle !== null) {
            try {
                $this->alertEngine->process($existing);
            } catch (\Throwable $exception) {
                Log::warning('alert.detection_failed', [
                    'position_id' => $existing->getKey(),
                    'vehicle_id' => $vehicle->getKey(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->broadcastPosition($equipment, $vehicle, $existing);

        return $existing;
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private static function mergeAttributes(?array $current, array $incoming, bool $preferIncoming): array
    {
        $current ??= [];

        $merged = $preferIncoming
            ? array_merge($current, $incoming)
            : array_merge($incoming, $current);

        $alarms = [];

        foreach ([$current['alarm'] ?? null, $incoming['alarm'] ?? null] as $value) {
            foreach (preg_split('/\s*,\s*/', (string) $value) ?: [] as $alarm) {
                $alarm = trim($alarm);

                if ($alarm !== '' && ! in_array(strtolower($alarm), array_map('strtolower', $alarms), true)) {
                    $alarms[] = $alarm;
                }
            }
        }

        if ($alarms !== []) {
            $merged['alarm'] = implode(',', $alarms);
        }

        return $merged;
    }

    /**
     * Horário do pacote com milissegundos, para decidir qual pacote prevalece
     * quando vários caem no mesmo segundo de `recorded_at`.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function withPacketTime(array $attributes, TraccarPosition $position): array
    {
        $attributes[self::PACKET_TIME_KEY] = $position->recordedAt->format('Y-m-d\TH:i:s.vP');

        return $attributes;
    }

    private function broadcastPosition(Equipment $equipment, ?Vehicle $vehicle, GpsPosition $gpsPosition): void
    {
        PositionUpdated::dispatch(
            $equipment->tenant?->uuid,
            $vehicle?->client?->uuid,
            [
                'vehicle_id' => $vehicle?->uuid,
                'equipment_id' => $equipment->uuid,
                'online' => true,
                'position' => GpsPositionResource::make($gpsPosition)->resolve(),
            ],
        );
    }

    /**
     * Mantém {@see Equipment::$traccar_last_update} alinhado ao ingest de posição,
     * para não ficar atrás de {@see GpsPosition::$recorded_at} entre syncs do Traccar.
     */
    private function refreshEquipmentLastConnection(Equipment $equipment, TraccarPosition $position): void
    {
        $signalAt = $position->recordedAt;

        if ($position->serverTime !== null && $position->serverTime->greaterThan($signalAt)) {
            $signalAt = $position->serverTime;
        }

        $current = $equipment->traccar_last_update;

        if ($current !== null && $current->greaterThanOrEqualTo($signalAt)) {
            return;
        }

        $equipment->forceFill([
            'traccar_last_update' => $signalAt,
            'traccar_status' => 'online',
        ])->save();
    }
}
