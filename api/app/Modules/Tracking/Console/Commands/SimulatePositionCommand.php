<?php

namespace App\Modules\Tracking\Console\Commands;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Tracking\Services\TraccarWebhookService;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SimulatePositionCommand extends Command
{
    protected $signature = 'tracking:simulate
        {plate : Placa do veículo}
        {--ignition=1 : Ignição (0/1)}
        {--motion= : Em movimento (0/1); padrão segue a velocidade}
        {--speed=0 : Velocidade em km/h}
        {--lat= : Latitude (padrão: última posição do veículo)}
        {--lng= : Longitude (padrão: última posição do veículo)}';

    protected $description = 'Simula uma posição do Traccar (entra pelo webhook) para testar o tempo real no painel';

    public function handle(TraccarWebhookService $webhook): int
    {
        $plate = strtoupper((string) $this->argument('plate'));

        $vehicle = null;
        $equipment = null;

        foreach (Vehicle::query()->withoutTenancy()->where('plate', $plate)->where('is_active', true)->get() as $candidate) {
            $candidateEquipment = Equipment::query()
                ->withoutTenancy()
                ->where('vehicle_id', $candidate->getKey())
                ->where('is_active', true)
                ->first();

            if ($candidateEquipment !== null) {
                $vehicle = $candidate;
                $equipment = $candidateEquipment;
                break;
            }
        }

        if ($vehicle === null || $equipment === null) {
            $this->error("Veículo {$plate} não encontrado (ou sem equipamento ativo).");

            return self::FAILURE;
        }

        $lastPosition = GpsPosition::query()
            ->withoutTenancy()
            ->where('vehicle_id', $vehicle->getKey())
            ->latest('recorded_at')
            ->first();

        $latitude = (float) ($this->option('lat') ?? $lastPosition?->latitude ?? -25.4284);
        $longitude = (float) ($this->option('lng') ?? $lastPosition?->longitude ?? -49.2733);
        $speed = (float) $this->option('speed');
        $ignition = (bool) ((int) $this->option('ignition'));
        $motion = $this->option('motion') !== null
            ? (bool) ((int) $this->option('motion'))
            : $speed > 0;
        $now = Carbon::now();

        $event = $webhook->ingest([
            'position' => [
                'id' => 0,
                'deviceId' => (int) ($equipment->traccar_device_id ?? 0),
                'protocol' => 'simulated',
                'serverTime' => $now->toIso8601String(),
                'deviceTime' => $now->format('Y-m-d\TH:i:s.vP'),
                'fixTime' => $now->toIso8601String(),
                'valid' => true,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'altitude' => 0,
                'speed' => $speed,
                'course' => 0,
                'accuracy' => 5,
                'attributes' => [
                    'ignition' => $ignition,
                    'motion' => $motion,
                    'batteryLevel' => 90,
                    'totalDistance' => data_get($lastPosition?->attributes, 'totalDistance', 0),
                    'hours' => data_get($lastPosition?->attributes, 'hours', 0),
                ],
            ],
            'device' => [
                'id' => (int) ($equipment->traccar_device_id ?? 0),
                'name' => $equipment->model ?? $plate,
                'uniqueId' => (string) $equipment->imei,
            ],
        ]);

        $this->info(sprintf(
            'Posição simulada para %s (IMEI %s): ignição=%s, movimento=%s, velocidade=%.0f km/h, evento #%d.',
            $plate,
            (string) $equipment->imei,
            $ignition ? 'ligada' : 'desligada',
            $motion ? 'sim' : 'não',
            $speed,
            $event->getKey(),
        ));

        return self::SUCCESS;
    }
}
