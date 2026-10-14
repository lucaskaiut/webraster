<?php

namespace App\Modules\Vehicle\Services;

use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Shared\Support\QuerySort;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleService
{
    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $clientId = null,
        ?int $excludeClientId = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        return $this->filteredQuery($search, $clientId, $excludeClientId, $sort)
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Exportação XLSX da listagem, respeitando os filtros aplicados.
     */
    public function export(?string $search = null, ?int $clientId = null, ?string $sort = null): StreamedResponse
    {
        $vehicles = $this->filteredQuery($search, $clientId, null, $sort)->get();

        return response()->streamDownload(function () use ($vehicles): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Veículos');

            $sheet->fromArray([
                'Placa',
                'Marca',
                'Modelo',
                'Ano',
                'Cor',
                'Cliente',
                'Chassi',
                'Renavam',
                'Equipamento (IMEI)',
                'Número da Linha',
                'Status',
                'Cadastrado em',
            ], null, 'A1');

            $row = 2;

            foreach ($vehicles as $vehicle) {
                $sheet->fromArray([
                    $vehicle->plate,
                    $vehicle->brand,
                    $vehicle->model,
                    $vehicle->year,
                    $vehicle->color,
                    $vehicle->client?->name,
                    $vehicle->chassis,
                    $vehicle->renavam,
                    $vehicle->equipment?->imei,
                    $vehicle->equipment?->line_number,
                    $vehicle->is_active ? 'Ativo' : 'Inativo',
                    $vehicle->created_at?->format('d/m/Y H:i:s'),
                ], null, "A{$row}");
                $row++;
            }

            $sheet->getStyle('A1:L1')->getFont()->setBold(true);

            foreach (range('A', 'L') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, 'veiculos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return Builder<Vehicle>
     */
    private function filteredQuery(
        ?string $search,
        ?int $clientId,
        ?int $excludeClientId = null,
        ?string $sort = null,
    ): Builder {
        $query = Vehicle::query()
            ->with(['client', 'equipment', 'images'])
            ->when($clientId !== null, function ($query) use ($clientId): void {
                // Veículos do cliente + veículos de outros clientes
                // compartilhados com ele.
                $query->where(function ($query) use ($clientId): void {
                    $query->where('client_id', $clientId)
                        ->orWhereHas(
                            'shares',
                            fn ($shares) => $shares->where('client_id', $clientId),
                        );
                });
            })
            ->when($excludeClientId !== null, fn ($query) => $query->where('client_id', '!=', $excludeClientId))
            ->when(filled($search), function ($query) use ($search): void {
                $normalized = $this->normalizePlate($search);

                $query->where(function ($query) use ($search, $normalized): void {
                    $query->where('plate', 'like', "%{$normalized}%")
                        ->orWhere('chassis', 'like', "%{$search}%")
                        ->orWhere('renavam', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($builder) use ($search): void {
                            $builder->where(function ($builder) use ($search): void {
                                $builder->where('name', 'like', "%{$search}%")
                                    ->orWhere('legal_name', 'like', "%{$search}%")
                                    ->orWhere('trade_name', 'like', "%{$search}%");
                            });
                        })
                        ->orWhereHas('equipment', function ($builder) use ($search): void {
                            $builder->where('imei', 'like', "%{$search}%")
                                ->orWhere('line_number', 'like', "%{$search}%");
                        });
                });
            });

        return QuerySort::apply(
            $query,
            $sort,
            ['plate', 'brand', 'model', 'color', 'year', 'odometer', 'is_active', 'created_at'],
            'plate:asc',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Vehicle
    {
        return DB::transaction(function () use ($data): Vehicle {
            $vehicle = Vehicle::query()->create($this->payload($data));

            if (isset($data['alert_configs']) && is_array($data['alert_configs'])) {
                app(AlertConfigService::class)->syncForVehicle($vehicle, $data['alert_configs']);
            }

            return $vehicle->load(['client', 'equipment', 'alertConfigs']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data): Vehicle {
            $vehicle->fill($this->payload($data));
            $vehicle->save();

            if (isset($data['alert_configs']) && is_array($data['alert_configs'])) {
                app(AlertConfigService::class)->syncForVehicle($vehicle, $data['alert_configs']);
            }

            return $vehicle->refresh()->load(['client', 'equipment', 'alertConfigs']);
        });
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
