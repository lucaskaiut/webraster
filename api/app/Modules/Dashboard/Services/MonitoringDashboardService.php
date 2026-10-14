<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Indicadores operacionais do dashboard de monitoramento.
 *
 * As faixas de offline não se sobrepõem: até 24h, de 24h a 36h e mais de 36h.
 * A referência é a última comunicação do veículo (posição persistida ou
 * lastUpdate do Traccar). Veículos sem nenhum sinal contam como +36h.
 */
class MonitoringDashboardService
{
    private const POSITION_ONLINE_MINUTES = 15;

    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $now = CarbonImmutable::now();
        $restricted = ClientAuthorization::isRestricted();

        $vehicles = Vehicle::query()
            ->where('is_active', true)
            ->whereHas('equipment')
            ->with(['equipment' => fn ($query) => $query->select('id', 'vehicle_id', 'traccar_last_update')])
            ->get(['id']);

        $lastSignals = $this->latestSignalsFor($vehicles->pluck('id')->all());

        $online = 0;
        $offlineUpTo24 = 0;
        $offline24To36 = 0;
        $offlineOver36 = 0;

        foreach ($vehicles as $vehicle) {
            $signal = $this->resolveSignal($vehicle, $lastSignals->get($vehicle->getKey()));

            if ($signal === null) {
                $offlineOver36++;

                continue;
            }

            if ($signal->greaterThan($now->subMinutes(self::POSITION_ONLINE_MINUTES))) {
                $online++;

                continue;
            }

            $hours = $signal->diffInHours($now);

            if ($hours < 24) {
                $offlineUpTo24++;
            } elseif ($hours < 36) {
                $offline24To36++;
            } else {
                $offlineOver36++;
            }
        }

        return [
            'vehicles' => [
                'total' => Vehicle::query()->count(),
                'online' => $online,
                'offline_up_to_24h' => $offlineUpTo24,
                'offline_24h_to_36h' => $offline24To36,
                'offline_over_36h' => $offlineOver36,
            ],
            // Usuário de portal não vê o estoque do tenant.
            'equipments' => $restricted ? null : [
                'active' => Equipment::query()->where('is_active', true)->count(),
                'in_stock' => Equipment::query()
                    ->where('is_active', true)
                    ->whereNull('vehicle_id')
                    ->count(),
            ],
            'chips' => $restricted ? null : [
                'in_stock' => Equipment::query()
                    ->where('is_active', true)
                    ->whereNull('vehicle_id')
                    ->whereNotNull('line_number')
                    ->where('line_number', '!=', '')
                    ->count(),
            ],
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $vehicleIds
     * @return Collection<int, CarbonImmutable>
     */
    private function latestSignalsFor(array $vehicleIds): Collection
    {
        if ($vehicleIds === []) {
            return collect();
        }

        return GpsPosition::query()
            ->select('vehicle_id')
            ->selectRaw('MAX(recorded_at) AS last_recorded_at')
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('vehicle_id')
            ->groupBy('vehicle_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->vehicle_id => CarbonImmutable::parse($row->last_recorded_at),
            ]);
    }

    private function resolveSignal(Vehicle $vehicle, ?CarbonImmutable $positionAt): ?CarbonImmutable
    {
        $lastUpdate = $vehicle->equipment?->traccar_last_update?->toImmutable();

        $candidates = collect([$positionAt, $lastUpdate])->filter()->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        /** @var CarbonImmutable $signal */
        $signal = $candidates->max();

        return $signal;
    }
}
