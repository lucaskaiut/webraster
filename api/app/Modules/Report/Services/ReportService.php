<?php

namespace App\Modules\Report\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Client\Support\Facades\ClientContext;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
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
            'period_horimeter' => $this->attributeFloat($attributes, ['engineHours', 'hours']),
            'onboard_horimeter' => $this->attributeFloat($attributes, ['hours']),
            'onboard_odometer' => $this->attributeFloat($attributes, ['odometer']),
            'battery' => $position->battery,
            'image' => $this->attributeString($attributes, ['image', 'imageUrl', 'photo']),
            'voltage' => $this->attributeFloat($attributes, ['power', 'voltage', 'batteryVoltage']),
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
            'equipment_imei' => $log->equipment?->imei,
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
            $log->equipment?->imei ?? '—',
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

    private function statusLabel(?DeviceCommandStatus $status): string
    {
        return match ($status) {
            DeviceCommandStatus::SUCCESS => 'Enviado',
            DeviceCommandStatus::FAILED => 'Falhou',
            default => 'Pendente',
        };
    }
}
