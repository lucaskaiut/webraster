<?php

namespace App\Modules\Report\Services;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Support\TraccarAttributeReader;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Client\Support\Facades\ClientContext;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    private const KNOTS_TO_KMH = 1.852;

    private const STOP_SPEED_THRESHOLD_KMH = 3.0;

    private const STOP_MIN_DURATION_MINUTES = 2;

    private const STOP_MAX_GAP_MINUTES = 10;

    private const COMMAND_LABELS = [
        'engineStop' => 'Bloquear Motor',
        'engineResume' => 'Desbloquear Motor',
        'alarmArm' => 'Armar Alarme',
        'alarmDisarm' => 'Desarmar Alarme',
        'custom' => 'Comando Personalizado',
        'deviceIdentification' => 'Identificação do dispositivo',
        'positionSingle' => 'Solicitar posição',
        'positionPeriodic' => 'Posição periódica',
        'positionStop' => 'Parar posições',
        'requestPhoto' => 'Solicitar foto',
        'powerOff' => 'Desligar dispositivo',
        'rebootDevice' => 'Reiniciar dispositivo',
        'factoryReset' => 'Restaurar fábrica',
        'setTimezone' => 'Definir fuso horário',
        'sosNumber' => 'Número SOS',
        'silenceTime' => 'Horário silencioso',
        'setPhonebook' => 'Agenda telefônica',
        'voiceMessage' => 'Mensagem de voz',
        'outputControl' => 'Controle de saída',
        'sendSms' => 'Enviar SMS',
    ];

    private const POSITION_COLUMNS = [
        'gps_date' => 'Data GPS',
        'gprs_date' => 'Data (GPRS)',
        'speed' => 'Velocidade (Km)',
        'ignition' => 'Ignição',
        'driver' => 'Motorista',
        'gps_status' => 'Status GPS',
        'gprs_status' => 'Status GPRS',
        'location' => 'Localização',
        'address' => 'Endereço',
        'event_type' => 'Tipo do Evento',
        'output' => 'Saída',
        'input' => 'Entrada',
        'package' => 'Pacote',
        'period_odometer' => 'Odômetro do período (Km)',
        'period_horimeter' => 'Horímetro do período',
        'onboard_horimeter' => 'Horímetro embarcado',
        'onboard_odometer' => 'Odômetro embarcado (Km)',
        'battery' => 'Bateria %',
        'image' => 'Imagem',
        'voltage' => 'Tensão (V)',
        'blocked' => 'Bloqueado',
    ];

    /**
     * Relatório "Comandos Enviados".
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function commands(array $filters = []): array
    {
        $rows = $this->commandsQuery($filters)
            ->get()
            ->map(fn (DeviceCommandLog $log) => $this->toRow($log))
            ->values();

        return [
            'rows' => $rows,
            'count' => $rows->count(),
        ];
    }

    /**
     * Exportação XLSX do relatório "Comandos Enviados".
     *
     * @param  array<string, mixed>  $filters
     */
    public function commandsExport(array $filters = []): StreamedResponse
    {
        $logs = $this->commandsQuery($filters)->get();

        return response()->streamDownload(function () use ($logs): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Comandos Enviados');

            $sheet->fromArray([
                'Equipamento (IMEI)',
                'Veículo (Placa - Modelo)',
                'Comando',
                'Data do evento',
                'Usuário',
                'Situação do envio',
            ], null, 'A1');

            $row = 2;

            foreach ($logs as $log) {
                $sheet->fromArray($this->toExportRow($log), null, "A{$row}");
                $row++;
            }

            $sheet->getStyle('A1:F1')->getFont()->setBold(true);

            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'comandos-enviados.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Relatório "Histórico de Posições" (veículo obrigatório).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function positions(array $filters = []): array
    {
        $vehicle = $this->resolveVehicle($filters);
        $rows = $this->positionsQuery($vehicle, $filters)
            ->get()
            ->map(fn (GpsPosition $position) => $this->toPositionRow($position))
            ->values();

        return [
            'vehicle' => trim("{$vehicle->plate} - {$vehicle->model}", ' -'),
            'rows' => $rows,
            'count' => $rows->count(),
        ];
    }

    /**
     * Exportação XLSX do relatório "Histórico de Posições".
     *
     * @param  array<string, mixed>  $filters
     */
    public function positionsExport(array $filters = []): StreamedResponse
    {
        $vehicle = $this->resolveVehicle($filters);
        $positions = $this->positionsQuery($vehicle, $filters)->get();
        $headers = array_values(self::POSITION_COLUMNS);
        $keys = array_keys(self::POSITION_COLUMNS);

        return response()->streamDownload(function () use ($positions, $headers, $keys): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Histórico de Posições');

            $sheet->fromArray($headers, null, 'A1');

            $row = 2;

            foreach ($positions as $position) {
                $data = $this->toPositionRow($position);
                $cells = [];

                foreach ($keys as $key) {
                    $cells[] = $this->formatPositionCell($key, $data[$key] ?? null);
                }

                $sheet->fromArray($cells, null, "A{$row}");
                $row++;
            }

            $sheet->getStyle('A1:'.$this->columnLetter(count($headers)).'1')->getFont()->setBold(true);

            foreach (range(1, count($headers)) as $index) {
                $sheet->getColumnDimension($this->columnLetter($index))->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'historico-posicoes.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Relatório "Paradas": por veículo, tempo total e quantidade de paradas.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function stops(array $filters = []): array
    {
        $clientId = $this->resolveClientId($filters);
        $rows = [];

        foreach ($this->vehiclesForReport($clientId) as $vehicle) {
            $summary = $this->summarizeStops(
                $this->positionsInPeriod($vehicle->getKey(), $filters),
            );

            $rows[] = [
                'vehicle_id' => $vehicle->uuid,
                'vehicle' => trim("{$vehicle->plate} - {$vehicle->model}", ' -'),
                'total_stops' => $summary['count'],
                'total_stop_seconds' => $summary['seconds'],
            ];
        }

        return [
            'rows' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * Exportação XLSX do relatório "Paradas".
     *
     * @param  array<string, mixed>  $filters
     */
    public function stopsExport(array $filters = []): StreamedResponse
    {
        $data = $this->stops($filters);

        return response()->streamDownload(function () use ($data): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Paradas');

            $sheet->fromArray([
                'Veículo (Placa - Modelo)',
                'Tempo total de paradas',
                'Número total de paradas',
            ], null, 'A1');

            $row = 2;

            foreach ($data['rows'] as $item) {
                $sheet->fromArray([
                    $item['vehicle'],
                    $this->formatDuration((int) $item['total_stop_seconds']),
                    (int) $item['total_stops'],
                ], null, "A{$row}");
                $row++;
            }

            $sheet->getStyle('A1:C1')->getFont()->setBold(true);

            foreach (range('A', 'C') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'relatorio-paradas.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Relatório "Percursos": identifica os deslocamentos (trechos entre paradas)
     * de um veículo no período, com resumo e trajeto por percurso.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function trips(array $filters = []): array
    {
        $vehicle = $this->resolveVehicle($filters);
        $positions = $this->positionsForTrip($vehicle->getKey(), $filters);
        $analysis = $this->analyzeTrips($positions);

        return [
            'vehicle' => trim("{$vehicle->plate} - {$vehicle->model}", ' -'),
            'summary' => $analysis['summary'],
            'trips' => $analysis['trips'],
        ];
    }

    /**
     * Relatório de eventos de excesso de velocidade consolidados.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function events(array $filters = []): array
    {
        $clientId = $this->resolveClientId($filters);
        $vehicleId = $this->resolveVehicleId($filters, $clientId);

        $query = Alert::query()
            ->with(['vehicle', 'client'])
            ->where('type', AlertType::SPEED->value)
            ->orderByDesc('occurred_at');

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        if ($vehicleId !== null) {
            $query->where('vehicle_id', $vehicleId);
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('occurred_at', '>=', CarbonImmutable::parse((string) $filters['from'])->toDateString());
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('occurred_at', '<=', CarbonImmutable::parse((string) $filters['to'])->toDateString());
        }

        $rows = $query->get()
            ->groupBy(function (Alert $alert): string {
                $meta = is_array($alert->meta) ? $alert->meta : [];
                $reference = filled($meta['started_at'] ?? null)
                    ? CarbonImmutable::parse((string) $meta['started_at'])
                    : ($alert->occurred_at !== null
                        ? CarbonImmutable::parse($alert->occurred_at)
                        : CarbonImmutable::now());

                return "{$alert->vehicle_id}|{$reference->toDateString()}";
            })
            ->map(function (Collection $alerts, string $key): array {
                /** @var Alert $first */
                $first = $alerts->first();
                $vehicle = $first->vehicle;
                [, $date] = explode('|', $key);

                $maxSpeedKmh = $alerts->reduce(function (float $max, Alert $alert): float {
                    $meta = is_array($alert->meta) ? $alert->meta : [];
                    $speed = (float) ($meta['max_speed_kmh'] ?? 0);

                    return max($max, $speed);
                }, 0.0);

                return [
                    'id' => ($vehicle?->uuid ?? 'unknown')."|{$date}",
                    'date' => $date,
                    'plate' => $vehicle?->plate,
                    'vehicle' => trim("{$vehicle?->brand} {$vehicle?->model}"),
                    'max_speed_kmh' => $maxSpeedKmh > 0 ? round($maxSpeedKmh, 1) : null,
                    'excess_count' => $alerts->count(),
                ];
            })
            ->sortBy([
                fn (array $row) => $row['date'],
                fn (array $row) => $row['plate'] ?? '',
            ], descending: [true, false])
            ->values();

        return [
            'rows' => $rows,
            'count' => $rows->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, GpsPosition>
     */
    private function positionsForTrip(int $vehicleId, array $filters): Collection
    {
        $query = GpsPosition::query()
            ->where('vehicle_id', $vehicleId)
            ->orderBy('recorded_at');

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('recorded_at', '>=', CarbonImmutable::parse((string) $filters['from'])->toDateString());
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('recorded_at', '<=', CarbonImmutable::parse((string) $filters['to'])->toDateString());
        }

        return $query->get([
            'id',
            'vehicle_id',
            'latitude',
            'longitude',
            'recorded_at',
            'speed',
            'ignition',
            'address',
            'attributes',
        ]);
    }

    /**
     * @param  Collection<int, GpsPosition>  $positions
     * @return array{summary: array<string, mixed>, trips: list<array<string, mixed>>}
     */
    private function analyzeTrips(Collection $positions): array
    {
        $stops = $this->detectStops($positions);
        $trips = [];
        $cursor = null;

        foreach ($stops as $stop) {
            $segment = $positions
                ->filter(fn (GpsPosition $position) => $position->recorded_at !== null
                    && ($cursor === null || $position->recorded_at->greaterThan($cursor))
                    && $position->recorded_at->lessThan($stop['start']))
                ->values();

            if ($segment->isNotEmpty()) {
                $trips[] = $this->buildTrip($segment, $stop['seconds']);
            }

            $cursor = $stop['end'];
        }

        $segment = $positions
            ->filter(fn (GpsPosition $position) => $position->recorded_at !== null
                && ($cursor === null || $position->recorded_at->greaterThan($cursor)))
            ->values();

        if ($segment->isNotEmpty()) {
            $trips[] = $this->buildTrip($segment, null);
        }

        return [
            'summary' => $this->buildTripsSummary($trips, $stops, $positions),
            'trips' => $trips,
        ];
    }

    /**
     * @param  Collection<int, GpsPosition>  $segment
     * @return array<string, mixed>
     */
    private function buildTrip(Collection $segment, ?int $followingStopSeconds): array
    {
        $first = $segment->first();
        $last = $segment->last();

        $startAt = $first?->recorded_at;
        $endAt = $last?->recorded_at;

        $movingSeconds = $startAt !== null && $endAt !== null
            ? abs($endAt->diffInSeconds($startAt))
            : 0;

        return [
            'id' => (string) ($first?->id ?? $startAt?->timestamp),
            'start_at' => $startAt?->toIso8601String(),
            'end_at' => $endAt?->toIso8601String(),
            'moving_seconds' => $movingSeconds,
            'following_stop_seconds' => $followingStopSeconds,
            'distance_meters' => $this->haversineTotal($segment),
            'origin' => $this->positionPoint($first),
            'destination' => $this->positionPoint($last),
            'driver' => $this->tripDriver($segment),
            'points' => $segment->map(fn (GpsPosition $position) => [
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'recorded_at' => $position->recorded_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $trips
     * @param  list<array{seconds: int}>  $stops
     * @param  Collection<int, GpsPosition>  $positions
     * @return array<string, mixed>
     */
    private function buildTripsSummary(array $trips, array $stops, Collection $positions): array
    {
        [$ignitionOn, $ignitionOff] = $this->ignitionTime($positions);

        return [
            'total_moving_seconds' => (int) array_sum(array_column($trips, 'moving_seconds')),
            'total_stopped_seconds' => (int) array_sum(array_column($stops, 'seconds')),
            'total_ignition_on_seconds' => $ignitionOn,
            'total_ignition_off_seconds' => $ignitionOff,
            'total_distance_meters' => round(array_sum(array_column($trips, 'distance_meters')), 1),
            'trips' => count($trips),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function positionPoint(?GpsPosition $position): ?array
    {
        if ($position === null) {
            return null;
        }

        $attributes = is_array($position->attributes) ? $position->attributes : [];

        return [
            'latitude' => $position->latitude,
            'longitude' => $position->longitude,
            'address' => $position->address ?: $this->attributeString($attributes, ['address']),
        ];
    }

    /**
     * @param  Collection<int, GpsPosition>  $segment
     */
    private function tripDriver(Collection $segment): ?string
    {
        foreach ($segment as $position) {
            $attributes = is_array($position->attributes) ? $position->attributes : [];
            $driver = $this->attributeString($attributes, ['driverName', 'driver', 'driverUniqueId']);

            if ($driver !== null) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, GpsPosition>  $segment
     */
    private function haversineTotal(Collection $segment): float
    {
        $distance = 0.0;
        $previous = null;

        foreach ($segment as $position) {
            if ($position->latitude === null || $position->longitude === null) {
                continue;
            }

            if ($previous !== null) {
                $distance += $this->haversineMeters(
                    $previous->latitude,
                    $previous->longitude,
                    $position->latitude,
                    $position->longitude,
                );
            }

            $previous = $position;
        }

        return round($distance, 1);
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * asin(sqrt($a));
    }

    /**
     * Tempo de ignição ligada/desligada (aproximado pela interpolação entre posições).
     *
     * @param  Collection<int, GpsPosition>  $positions
     * @return array{0: int, 1: int}
     */
    private function ignitionTime(Collection $positions): array
    {
        $on = 0;
        $off = 0;
        $previous = null;

        foreach ($positions as $position) {
            if ($position->recorded_at === null) {
                continue;
            }

            if ($previous !== null && $previous->recorded_at !== null) {
                $interval = abs($position->recorded_at->diffInSeconds($previous->recorded_at));

                if ($previous->ignition === true) {
                    $on += $interval;
                } elseif ($previous->ignition === false) {
                    $off += $interval;
                }
            }

            $previous = $position;
        }

        return [$on, $off];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<GpsPosition>
     */
    private function positionsQuery(Vehicle $vehicle, array $filters): Builder
    {
        $query = GpsPosition::query()->where('vehicle_id', $vehicle->getKey());

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('recorded_at', '>=', CarbonImmutable::parse((string) $filters['from'])->toDateString());
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('recorded_at', '<=', CarbonImmutable::parse((string) $filters['to'])->toDateString());
        }

        return $query->orderBy('recorded_at');
    }

    /**
     * Resolve o veículo obrigatório do relatório de posições (respeitando o escopo do cliente).
     *
     * @param  array<string, mixed>  $filters
     */
    private function resolveVehicle(array $filters): Vehicle
    {
        $vehicleId = $filters['vehicle_id'] ?? null;

        if (blank($vehicleId)) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Selecione um veículo para consultar o histórico de posições.'],
            ]);
        }

        $clientId = $this->resolveClientId($filters);

        $query = Vehicle::query()->where('uuid', (string) $vehicleId);

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        $vehicle = $query->first();

        if ($vehicle === null) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Veículo não encontrado.'],
            ]);
        }

        return $vehicle;
    }

    /**
     * @return array<string, mixed>
     */
    private function toPositionRow(GpsPosition $position): array
    {
        $attributes = is_array($position->attributes) ? $position->attributes : [];

        return [
            'id' => $position->uuid,
            'gps_date' => $position->recorded_at?->toIso8601String(),
            'gprs_date' => $position->server_time?->toIso8601String(),
            'speed' => $position->speed,
            'ignition' => $position->ignition,
            'driver' => $this->attributeString($attributes, ['driverName', 'driver', 'driverUniqueId']),
            'gps_status' => $position->valid,
            'gprs_status' => $this->attributeString($attributes, ['gprsStatus', 'gsmStatus', 'gprs']),
            'location' => $position->latitude !== null && $position->longitude !== null
                ? "{$position->latitude}, {$position->longitude}"
                : null,
            'address' => $position->address ?: $this->attributeString($attributes, ['address']),
            'event_type' => $this->attributeString($attributes, ['event', 'alarm']),
            'output' => $this->attributeString($attributes, ['output', 'outputState', 'relayState']),
            'input' => $this->attributeString($attributes, ['input', 'inputState', 'ioState']),
            'package' => $this->attributeString($attributes, ['protocol']),
            'period_odometer' => $this->attributeFloat($attributes, ['totalDistance']),
            'period_horimeter' => $this->attributeHours($attributes, ['engineHours', 'hours']),
            'onboard_horimeter' => $this->attributeHours($attributes, ['hours']),
            'onboard_odometer' => $this->attributeKilometers($attributes, ['odometer']),
            'battery' => $position->battery,
            'image' => $this->attributeString($attributes, ['image', 'imageUrl', 'photo']),
            'voltage' => TraccarAttributeReader::voltage($attributes),
            'blocked' => $this->attributeBool($attributes, ['blocked', 'lock', 'relay', 'engineBlocked']),
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    private function attributeString(array $attributes, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($attributes[$key]) && $attributes[$key] !== '') {
                return (string) $attributes[$key];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function attributeFloat(array $attributes, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($attributes[$key]) && is_numeric($attributes[$key])) {
                return (float) $attributes[$key];
            }
        }

        return null;
    }

    private function attributeHours(array $attributes, array $keys): ?float
    {
        $value = $this->attributeFloat($attributes, $keys);

        return $value === null ? null : round($value / 3_600_000, 2);
    }

    private function attributeKilometers(array $attributes, array $keys): ?float
    {
        $value = $this->attributeFloat($attributes, $keys);

        return $value === null ? null : round($value / 1000, 2);
    }

    /**
     * @param  list<string>  $keys
     */
    private function attributeBool(array $attributes, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $attributes)) {
                return filter_var($attributes[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return null;
    }

    private function formatPositionCell(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return '—';
        }

        return match ($key) {
            'ignition', 'blocked' => $value ? 'Sim' : 'Não',
            'gps_status' => $value ? 'Válido' : 'Inválido',
            'gps_date', 'gprs_date' => CarbonImmutable::parse($value)->format('d/m/Y H:i:s'),
            'battery', 'voltage' => is_numeric($value) ? (float) $value : $value,
            default => $value,
        };
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    /**
     * Veículos monitorados (ativos e com equipamento) no escopo do cliente.
     *
     * @return Collection<int, Vehicle>
     */
    private function vehiclesForReport(?int $clientId): Collection
    {
        return Vehicle::query()
            ->where('is_active', true)
            ->whereHas('equipment', fn ($query) => $query->where('is_active', true))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->orderBy('plate')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, GpsPosition>
     */
    private function positionsInPeriod(int $vehicleId, array $filters): Collection
    {
        $query = GpsPosition::query()
            ->where('vehicle_id', $vehicleId)
            ->orderBy('recorded_at');

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('recorded_at', '>=', CarbonImmutable::parse((string) $filters['from'])->toDateString());
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('recorded_at', '<=', CarbonImmutable::parse((string) $filters['to'])->toDateString());
        }

        return $query->get(['id', 'vehicle_id', 'recorded_at', 'speed', 'ignition']);
    }

    /**
     * @param  Collection<int, GpsPosition>  $positions
     * @return array{count: int, seconds: int}
     */
    private function summarizeStops(Collection $positions): array
    {
        $stops = $this->detectStops($positions);

        return [
            'count' => count($stops),
            'seconds' => array_sum(array_column($stops, 'seconds')),
        ];
    }

    /**
     * Detecção de paradas por máquina de estados sobre a sequência de posições.
     *
     * @param  Collection<int, GpsPosition>  $positions
     * @return list<array{start: CarbonInterface, end: CarbonInterface, seconds: int}>
     */
    private function detectStops(Collection $positions): array
    {
        $thresholdKmh = self::STOP_SPEED_THRESHOLD_KMH;
        $minSeconds = self::STOP_MIN_DURATION_MINUTES * 60;
        $maxGapSeconds = self::STOP_MAX_GAP_MINUTES * 60;

        $stops = [];
        $start = null;
        $end = null;
        $previousAt = null;

        foreach ($positions as $position) {
            $recordedAt = $position->recorded_at;

            if ($recordedAt === null) {
                continue;
            }

            if ($previousAt !== null && abs($recordedAt->diffInSeconds($previousAt)) > $maxGapSeconds) {
                if ($start !== null && $end !== null) {
                    $this->pushStop($stops, $start, $end, $minSeconds);
                }

                $start = null;
                $end = null;
            }

            if ($this->isStopped($position, $thresholdKmh)) {
                if ($start === null) {
                    $start = $recordedAt;
                }

                $end = $recordedAt;
            } else {
                if ($start !== null && $end !== null) {
                    $this->pushStop($stops, $start, $end, $minSeconds);
                }

                $start = null;
                $end = null;
            }

            $previousAt = $recordedAt;
        }

        if ($start !== null && $end !== null) {
            $this->pushStop($stops, $start, $end, $minSeconds);
        }

        return $stops;
    }

    private function isStopped(GpsPosition $position, float $thresholdKmh): bool
    {
        if ($position->ignition === false) {
            return true;
        }

        if ($position->speed !== null) {
            return $position->speed * self::KNOTS_TO_KMH <= $thresholdKmh;
        }

        return false;
    }

    /**
     * @param  list<array{start: CarbonInterface, end: CarbonInterface, seconds: int}>  $stops
     */
    private function pushStop(array &$stops, CarbonInterface $start, CarbonInterface $end, int $minSeconds): void
    {
        $seconds = abs($end->diffInSeconds($start));

        if ($seconds >= $minSeconds) {
            $stops[] = [
                'start' => $start,
                'end' => $end,
                'seconds' => $seconds,
            ];
        }
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours === 0) {
            return "{$minutes}min";
        }

        return "{$hours}h {$minutes}min";
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<DeviceCommandLog>
     */
    private function commandsQuery(array $filters): Builder
    {
        $clientId = $this->resolveClientId($filters);
        $vehicleId = $this->resolveVehicleId($filters, $clientId);

        $query = DeviceCommandLog::query()
            ->with(['equipment:id,uuid,imei', 'vehicle:id,uuid,plate,model', 'user:id,uuid,name']);

        if ($clientId !== null) {
            $query->whereHas('vehicle', fn (Builder $q) => $q->where('client_id', $clientId));
        }

        if ($vehicleId !== null) {
            $query->where('vehicle_id', $vehicleId);
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('requested_at', '>=', CarbonImmutable::parse((string) $filters['from'])->toDateString());
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('requested_at', '<=', CarbonImmutable::parse((string) $filters['to'])->toDateString());
        }

        return $query->orderByDesc('requested_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveClientId(array $filters): ?int
    {
        if (ClientAuthorization::isRestricted()) {
            return ClientContext::clientId();
        }

        $clientId = $filters['client_id'] ?? null;

        if (blank($clientId)) {
            return null;
        }

        return Client::query()->where('uuid', (string) $clientId)->value('id') ?: null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveVehicleId(array $filters, ?int $clientId): ?int
    {
        $vehicleId = $filters['vehicle_id'] ?? null;

        if (blank($vehicleId)) {
            return null;
        }

        $query = Vehicle::query()->where('uuid', (string) $vehicleId);

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        return $query->value('id') ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(DeviceCommandLog $log): array
    {
        return [
            'id' => $log->uuid,
            'equipment_imei' => $this->canViewEquipmentDetails() ? $log->equipment?->imei : null,
            'vehicle' => $log->vehicle
                ? trim("{$log->vehicle->plate} - {$log->vehicle->model}", ' -')
                : null,
            'command_type' => $log->command_type,
            'command_label' => $this->commandLabel($log->command_type),
            'requested_at' => $log->requested_at?->toIso8601String(),
            'user' => $log->user?->name,
            'status' => $log->status?->value,
            'status_label' => $this->statusLabel($log->status),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function toExportRow(DeviceCommandLog $log): array
    {
        return [
            $this->canViewEquipmentDetails() ? ($log->equipment?->imei ?? '—') : '—',
            $log->vehicle ? trim("{$log->vehicle->plate} - {$log->vehicle->model}", ' -') : '—',
            $this->commandLabel($log->command_type),
            $log->requested_at?->format('d/m/Y H:i:s') ?? '—',
            $log->user?->name ?? '—',
            $this->statusLabel($log->status),
        ];
    }

    private function commandLabel(string $type): string
    {
        return self::COMMAND_LABELS[$type] ?? $type;
    }

    private function canViewEquipmentDetails(): bool
    {
        return (bool) request()->user()?->hasPermission(Permission::EQUIPMENT_DETAILS_READ);
    }

    private function statusLabel(?DeviceCommandStatus $status): string
    {
        return match ($status) {
            DeviceCommandStatus::SUCCESS => 'Enviado',
            DeviceCommandStatus::FAILED => 'Falhou',
            default => 'Pendente',
        };
    }
}
