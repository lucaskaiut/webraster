<?php

namespace Database\Seeders\Demo;

use App\Modules\Alert\Enums\AlertSeverity;
use App\Modules\Alert\Enums\AlertStatus;
use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Client\Models\Client;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Geofence\Enums\GeofenceEventType;
use App\Modules\Geofence\Enums\GeofenceType;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Models\VehicleGeofenceState;
use App\Modules\Notification\Enums\NotificationSource;
use App\Modules\Poi\Models\Poi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Histórico de posições dos últimos dias seguindo rotas reais da região de
 * Curitiba, com veículos parados, em movimento agora e offline. Gera
 * também geocercas, pontos de interesse, eventos de geocerca, alertas,
 * comandos enviados e as notificações da equipe e dos clientes.
 */
class DemoTrackingSeeder extends Seeder
{
    private const DAYS = 7;

    private const KNOTS = 1.852;

    private const SAMPLE_SECONDS = 30;

    private const PARKED_SECONDS = 20 * 60;

    private const GEOFENCE_RADIUS = 400;

    private DemoContext $ctx;

    /** @var list<array<string, mixed>> */
    private array $positionRows = [];

    /** @var list<array<string, mixed>> */
    private array $alertRows = [];

    /** @var list<array<string, mixed>> */
    private array $geofenceEventRows = [];

    /** @var array<int, Geofence> client_id => geofence da base */
    private array $baseGeofences = [];

    public function run(DemoContext $ctx): void
    {
        $this->ctx = $ctx;

        $this->seedPlaces();

        foreach ($ctx->fleet as $index => $entry) {
            if ($entry['equipment'] === null || ! $entry['client']->is_active) {
                continue;
            }

            $this->seedVehicleHistory($entry, $index);
        }

        $this->flushPositions();
        $this->seedSpecialAlerts();
        $this->insertChunked('geofence_events', $this->geofenceEventRows);
        $this->insertChunked('alerts', $this->alertRows);
        $this->seedCommands();
        $this->seedNotifications();
    }

    /**
     * Geocerca da garagem e pontos de interesse por cliente, além de
     * algumas geocercas de região para as maiores frotas.
     */
    private function seedPlaces(): void
    {
        $ctx = $this->ctx;
        $faker = $ctx->faker;
        $placeNames = [
            'centro' => 'Centro de Curitiba', 'batel' => 'Batel', 'cic' => 'Cidade Industrial', 'aeroporto' => 'Aeroporto Afonso Pena',
            'portao' => 'Portão', 'boqueirao' => 'Boqueirão', 'santa_felicidade' => 'Santa Felicidade', 'bacacheri' => 'Bacacheri',
            'pinhais' => 'Pinhais', 'colombo' => 'Colombo', 'araucaria' => 'Araucária', 'fazenda_rio_grande' => 'Fazenda Rio Grande',
            'hauer' => 'Hauer', 'jardim_botanico' => 'Jardim Botânico', 'rodoferroviaria' => 'Rodoferroviária', 'campo_largo' => 'Campo Largo',
            'agua_verde' => 'Água Verde', 'cabral' => 'Cabral',
        ];

        foreach ($ctx->clients as $entry) {
            /** @var Client $client */
            $client = $entry['client'];

            if (! $client->is_active) {
                continue;
            }

            $company = $entry['kind'] === 'company';

            Poi::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'client_id' => $client->getKey(),
                'poi_category_id' => $ctx->poiCategories['base']->getKey(),
                'name' => $company ? "Garagem — {$client->name}" : 'Casa',
                'latitude' => $client->latitude,
                'longitude' => $client->longitude,
                'address' => $client->geocoded_address,
                'is_active' => true,
                'created_at' => $entry['since'],
            ]);

            foreach (array_slice($ctx->routesFrom($entry['base']), 0, $company ? 3 : 1) as $route) {
                $point = $ctx->place($route['to']);
                $category = $company ? $faker->randomElement(['customer', 'delivery', 'fuel', 'workshop']) : 'customer';
                $name = match ($category) {
                    'customer' => $company ? 'Cliente — '.$placeNames[$route['to']] : 'Trabalho',
                    'delivery' => 'Ponto de entrega — '.$placeNames[$route['to']],
                    'fuel' => 'Posto conveniado — '.$placeNames[$route['to']],
                    default => 'Oficina parceira — '.$placeNames[$route['to']],
                };

                Poi::query()->create([
                    'tenant_id' => $ctx->tenant->getKey(),
                    'client_id' => $client->getKey(),
                    'poi_category_id' => $ctx->poiCategories[$category]->getKey(),
                    'name' => $name,
                    'latitude' => $point['lat'],
                    'longitude' => $point['lng'],
                    'is_active' => true,
                    'created_at' => $entry['since']->addDays(3),
                ]);
            }

            if (! $company) {
                continue;
            }

            $this->baseGeofences[$client->getKey()] = $this->circle($client, "Garagem — {$client->name}", 'Pátio e garagem da empresa.', $ctx->place($entry['base']), self::GEOFENCE_RADIUS, $entry['since']);
        }

        $byName = collect($ctx->clients)->keyBy(fn ($entry) => $entry['client']->name);

        if ($pinheiro = $byName->get('Transportadora Pinheiro')) {
            $this->polygon($pinheiro['client'], 'Região Metropolitana de Curitiba', 'Área de atuação permitida para a frota.', [
                [-25.2600, -49.2400], [-25.3300, -49.0900], [-25.5500, -49.0800], [-25.7100, -49.2800],
                [-25.6400, -49.4600], [-25.4700, -49.5800], [-25.3200, -49.4200],
            ]);
            $this->circle($pinheiro['client'], 'CEASA Curitiba', 'Centro de abastecimento — janela de descarga 04h às 10h.', ['lat' => -25.4887, 'lng' => -49.2457], 350);
        }

        if ($distribuidora = $byName->get('Distribuidora Araucária')) {
            $this->circle($distribuidora['client'], 'Refinaria / Polo Industrial de Araucária', 'Área de risco — reduzir velocidade.', ['lat' => -25.5690, 'lng' => -49.3460], 1200);
        }

        if ($locadora = $byName->get('Locadora Movimenta')) {
            $this->circle($locadora['client'], 'Aeroporto Afonso Pena — estacionamento', 'Devolução de veículos.', $ctx->place('aeroporto'), 600);
        }
    }

    /**
     * @param  array{lat: float, lng: float}  $center
     */
    private function circle(Client $client, string $name, string $description, array $center, int $radius, ?CarbonImmutable $at = null): Geofence
    {
        $latDelta = $radius / 111_320;
        $lngDelta = $radius / (111_320 * cos(deg2rad($center['lat'])));

        return Geofence::query()->create([
            'tenant_id' => $this->ctx->tenant->getKey(),
            'client_id' => $client->getKey(),
            'name' => $name,
            'description' => $description,
            'type' => GeofenceType::CIRCLE,
            'is_active' => true,
            'center_latitude' => $center['lat'],
            'center_longitude' => $center['lng'],
            'radius_meters' => $radius,
            'bbox_min_lat' => $center['lat'] - $latDelta,
            'bbox_max_lat' => $center['lat'] + $latDelta,
            'bbox_min_lng' => $center['lng'] - $lngDelta,
            'bbox_max_lng' => $center['lng'] + $lngDelta,
            'created_at' => $at ?? $this->ctx->now->subMonths(3),
        ]);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private function polygon(Client $client, string $name, string $description, array $points): void
    {
        $lats = array_column($points, 0);
        $lngs = array_column($points, 1);

        Geofence::query()->create([
            'tenant_id' => $this->ctx->tenant->getKey(),
            'client_id' => $client->getKey(),
            'name' => $name,
            'description' => $description,
            'type' => GeofenceType::POLYGON,
            'is_active' => true,
            'geometry' => array_map(fn ($point) => ['latitude' => $point[0], 'longitude' => $point[1]], $points),
            'bbox_min_lat' => min($lats),
            'bbox_max_lat' => max($lats),
            'bbox_min_lng' => min($lngs),
            'bbox_max_lng' => max($lngs),
            'created_at' => $this->ctx->now->subMonths(4),
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function seedVehicleHistory(array $entry, int $index): void
    {
        $ctx = $this->ctx;
        $faker = $ctx->faker;
        /** @var Equipment $equipment */
        $equipment = $entry['equipment'];

        $end = match ($entry['signal']) {
            'offline_recent' => $ctx->now->subMinutes($faker->numberBetween(120, 1080)),
            'offline_day' => $ctx->now->subMinutes($faker->numberBetween(25 * 60, 34 * 60)),
            'offline_long' => $ctx->now->subMinutes($faker->numberBetween(3 * 1440, 9 * 1440)),
            'suspended' => CarbonImmutable::parse($equipment->billing_suspended_at),
            default => $ctx->now->subSeconds($faker->numberBetween(20, 240)),
        };
        $start = $ctx->now->subDays(self::DAYS)->startOfDay();

        if ($entry['installed_at'] !== null && $entry['installed_at']->greaterThan($start)) {
            $start = $entry['installed_at']->addMinutes(20);
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->addHours(2);
        }

        $base = $ctx->place($entry['base']);
        $routes = $ctx->routesFrom($entry['base']);
        $movingNow = $entry['signal'] === 'online' && $entry['usage'] !== 'site' && $index % 3 === 0;
        $trips = $this->planTrips($entry, $routes, $start, $end, $movingNow);

        $state = [
            'entry' => $entry,
            'odometer' => (float) $entry['vehicle']->odometer * 1000,
            'total' => 0.0,
            'hours' => $faker->numberBetween(800, 9000) * 3_600_000,
            'last' => null,
            'positions' => [],
            'inside' => true,
            'last_geofence_at' => null,
        ];

        $cursor = $start;
        $location = $base;

        foreach ($trips as $trip) {
            // Viagem anterior ainda em curso: sai logo depois de chegar.
            if ($trip['depart']->lessThan($cursor)) {
                $trip['depart'] = $cursor->addMinutes(10);
            }

            $this->parked($state, $location, $cursor, $trip['depart']->min($end));

            if ($trip['depart']->greaterThanOrEqualTo($end)) {
                break;
            }

            [$location, $cursor] = $entry['usage'] === 'site'
                ? $this->wander($state, $base, $trip['depart'], $trip['minutes'], $end)
                : $this->drive($state, $trip, $end);

            if ($cursor->greaterThanOrEqualTo($end)) {
                break;
            }
        }

        if ($cursor->lessThan($end)) {
            $this->parked($state, $location, $cursor, $end, true);
        }

        $last = $state['last'];

        Equipment::query()->whereKey($equipment->getKey())->update([
            'traccar_status' => $entry['signal'] === 'online' ? 'online' : 'offline',
            'traccar_last_update' => $last['recorded_at'] ?? null,
        ]);

        if (isset($this->baseGeofences[$entry['client']->getKey()])) {
            VehicleGeofenceState::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'vehicle_id' => $entry['vehicle']->getKey(),
                'geofence_id' => $this->baseGeofences[$entry['client']->getKey()]->getKey(),
                'is_inside' => $state['inside'],
                'last_recorded_at' => $state['last_geofence_at'] ?? $last['recorded_at'] ?? null,
            ]);
        }

        $this->vehicleAlerts($state, $end);
    }

    /**
     * Viagens de ida e volta a partir da base, conforme o perfil de uso.
     *
     * @param  array<string, mixed>  $entry
     * @param  list<array<string, mixed>>  $routes
     * @return list<array<string, mixed>>
     */
    private function planTrips(array $entry, array $routes, CarbonImmutable $start, CarbonImmutable $end, bool $movingNow): array
    {
        $faker = $this->ctx->faker;
        $trips = [];
        $day = $start->setTimezone(DemoContext::TIMEZONE)->startOfDay();
        $lastDay = $end->setTimezone(DemoContext::TIMEZONE)->startOfDay();

        while ($day->lessThanOrEqualTo($lastDay)) {
            $weekend = $day->isWeekend();
            $rounds = match ($entry['usage']) {
                'heavy' => $weekend ? ($day->isSaturday() && $faker->boolean(50) ? [[6, 30]] : []) : [[$faker->numberBetween(5, 7), 0]],
                'fleet' => $weekend ? ($day->isSaturday() && $faker->boolean(35) ? [[9, 0]] : []) : [[$faker->numberBetween(7, 8), 30], [$faker->numberBetween(13, 14), 15]],
                'personal' => $weekend ? ($faker->boolean(55) ? [[$faker->numberBetween(10, 15), 0]] : []) : [[7, $faker->numberBetween(0, 50)]],
                default => $weekend ? [] : [[8, 0], [10, 30], [14, 0], [16, 0]],
            };

            foreach ($rounds as [$hour, $minute]) {
                $depart = $day->setTime($hour, $minute)->addMinutes($faker->numberBetween(0, 40))->utc();

                if ($entry['usage'] === 'site') {
                    $trips[] = ['depart' => $depart, 'minutes' => $faker->numberBetween(20, 50)];

                    continue;
                }

                $route = $faker->randomElement($routes);
                $dwell = match ($entry['usage']) {
                    'heavy' => $faker->numberBetween(90, 240),
                    'personal' => $weekend ? $faker->numberBetween(60, 180) : $faker->numberBetween(8 * 60, 10 * 60),
                    default => $faker->numberBetween(30, 120),
                };

                $trips[] = ['depart' => $depart, 'route' => $route, 'dwell' => $dwell];
            }

            $day = $day->addDay();
        }

        if ($movingNow && $routes !== []) {
            $route = $this->ctx->faker->randomElement($routes);
            $elapsed = (int) ($route['duration_s'] * $faker->randomFloat(2, 0.25, 0.75));
            $depart = $this->ctx->now->subSeconds($elapsed);

            $trips = array_values(array_filter($trips, fn ($trip) => $trip['depart']->lessThan($depart->subHours(10))));
            $trips[] = ['depart' => $depart, 'route' => $route, 'dwell' => 0, 'one_way' => true];
        }

        return $trips;
    }

    /**
     * Ida até o destino, permanência e volta à base.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $trip
     * @return array{0: array{lat: float, lng: float}, 1: CarbonImmutable}
     */
    private function drive(array &$state, array $trip, CarbonImmutable $end): array
    {
        $route = $trip['route'];
        $usage = $state['entry']['usage'];
        $speeding = in_array($usage, ['heavy', 'fleet'], true) && $route['distance_m'] > 8000 && $this->ctx->faker->boolean(15);
        $companyGeofence = isset($this->baseGeofences[$state['entry']['client']->getKey()]);

        $arrive = $this->followRoute($state, $route['points'], $route, $trip['depart'], $end, $speeding, $companyGeofence ? 'exit' : null);
        $destination = ['lat' => $route['points'][array_key_last($route['points'])][0], 'lng' => $route['points'][array_key_last($route['points'])][1]];

        if ($arrive->greaterThanOrEqualTo($end) || ($trip['one_way'] ?? false)) {
            return [$destination, $arrive];
        }

        $leave = $arrive->addMinutes($trip['dwell']);
        $this->parked($state, $destination, $arrive, $leave->min($end));

        if ($leave->greaterThanOrEqualTo($end)) {
            return [$destination, $end];
        }

        $back = $this->followRoute($state, array_reverse($route['points']), $route, $leave, $end, false, $companyGeofence ? 'entry' : null);

        return [$this->ctx->place($state['entry']['base']), $back];
    }

    /**
     * Amostra o trajeto a cada 30 segundos, com paradas de semáforo,
     * velocidade variável e, opcionalmente, um trecho acima do limite.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array{0: float, 1: float}>  $points
     * @param  array<string, mixed>  $route
     */
    private function followRoute(array &$state, array $points, array $route, CarbonImmutable $depart, CarbonImmutable $end, bool $speeding, ?string $geofenceEvent): CarbonImmutable
    {
        $faker = $this->ctx->faker;
        $usage = $state['entry']['usage'];
        $factor = $usage === 'heavy' ? $faker->randomFloat(2, 1.25, 1.5) : $faker->randomFloat(2, 1.05, 1.35);
        $duration = max(120, (int) ($route['duration_s'] * $factor));
        $cumulative = [0.0];

        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $cumulative[$i] = $cumulative[$i - 1] + $this->distance($points[$i - 1], $points[$i]);
        }

        $total = end($cumulative);
        $avgKmh = ($total / $duration) * 3.6;
        $limit = (int) ($state['entry']['vehicle']->max_speed_kmh ?: 80);
        $speedWindow = $speeding ? [(int) ($duration * 0.45), (int) ($duration * 0.45) + $faker->numberBetween(90, 180)] : null;
        $speedSamples = [];
        $segment = 0;
        $previous = $points[0];

        $this->push($state, $points[0], $depart, 0, true, false, 0.0);

        for ($t = self::SAMPLE_SECONDS; $t <= $duration; $t += self::SAMPLE_SECONDS) {
            $at = $depart->addSeconds($t);

            if ($at->greaterThanOrEqualTo($end)) {
                return $end;
            }

            $target = $total * ($t / $duration);

            while ($segment < count($cumulative) - 2 && $cumulative[$segment + 1] < $target) {
                $segment++;
            }

            $span = max(1.0, $cumulative[$segment + 1] - $cumulative[$segment]);
            $ratio = min(1.0, ($target - $cumulative[$segment]) / $span);
            $point = [
                $points[$segment][0] + ($points[$segment + 1][0] - $points[$segment][0]) * $ratio,
                $points[$segment][1] + ($points[$segment + 1][1] - $points[$segment][1]) * $ratio,
            ];

            $inWindow = $speedWindow !== null && $t >= $speedWindow[0] && $t <= $speedWindow[1];
            $kmh = match (true) {
                $inWindow => $limit + $faker->numberBetween(8, 26),
                $faker->numberBetween(1, 100) <= 7 => 0.0,
                default => max(5.0, min($limit - 2, $avgKmh * $faker->randomFloat(2, 0.55, 1.6))),
            };

            $step = $this->distance($previous, $point);
            $this->push($state, $point, $at, $this->bearing($previous, $point), true, $kmh > 0, $step, $kmh);

            if ($inWindow) {
                $speedSamples[] = ['at' => $at, 'kmh' => $kmh, 'point' => $point];
            }

            if ($geofenceEvent === 'exit' && $t === self::SAMPLE_SECONDS * 2) {
                $this->geofenceEvent($state, GeofenceEventType::EXIT, $point, $at, $kmh);
            }

            $previous = $point;
        }

        $arrival = $depart->addSeconds($duration + self::SAMPLE_SECONDS);

        if ($arrival->greaterThanOrEqualTo($end)) {
            return $end;
        }

        $last = $points[array_key_last($points)];

        if ($geofenceEvent === 'entry') {
            $this->geofenceEvent($state, GeofenceEventType::ENTRY, $last, $arrival->subSeconds(20), 12);
        }

        $this->push($state, $last, $arrival, $this->bearing($previous, $last), false, false, $this->distance($previous, $last));

        if (count($speedSamples) >= 3) {
            $this->speedAlert($state, $speedSamples, $limit);
        }

        return $arrival;
    }

    /**
     * Máquinas de obra/campo: deslocamentos curtos e lentos em volta da base.
     *
     * @param  array<string, mixed>  $state
     * @param  array{lat: float, lng: float}  $base
     * @return array{0: array{lat: float, lng: float}, 1: CarbonImmutable}
     */
    private function wander(array &$state, array $base, CarbonImmutable $depart, int $minutes, CarbonImmutable $end): array
    {
        $faker = $this->ctx->faker;
        $point = [$base['lat'], $base['lng']];
        $at = $depart;

        $this->push($state, $point, $at, 0, true, false, 0.0);

        for ($t = 60; $t <= $minutes * 60; $t += 60) {
            $at = $depart->addSeconds($t);

            if ($at->greaterThanOrEqualTo($end)) {
                return [['lat' => $point[0], 'lng' => $point[1]], $end];
            }

            $next = $this->ctx->near($base['lat'], $base['lng'], 250);
            $next = [$next['lat'], $next['lng']];
            $kmh = $faker->numberBetween(2, 14);
            $this->push($state, $next, $at, $this->bearing($point, $next), true, true, $this->distance($point, $next), $kmh);
            $point = $next;
        }

        $at = $at->addSeconds(30);
        $this->push($state, [$base['lat'], $base['lng']], $at, 0, false, false, $this->distance($point, [$base['lat'], $base['lng']]));

        return [$base, $at];
    }

    /**
     * Veículo desligado: posições de manutenção a cada 20 minutos.
     *
     * @param  array<string, mixed>  $state
     * @param  array{lat: float, lng: float}  $location
     */
    private function parked(array &$state, array $location, CarbonImmutable $from, CarbonImmutable $until, bool $final = false): void
    {
        $point = [$location['lat'], $location['lng']];
        $at = $from->addSeconds(self::PARKED_SECONDS);

        while ($at->lessThan($until)) {
            $this->push($state, $point, $at, $state['last']['heading'] ?? 0, false, false, 0.0);
            $at = $at->addSeconds(self::PARKED_SECONDS);
        }

        if ($final && ($state['last'] === null || $state['last']['recorded_at'] < $until->format('Y-m-d H:i:s'))) {
            $this->push($state, $point, $until, $state['last']['heading'] ?? 0, false, false, 0.0);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array{0: float, 1: float}  $point
     */
    private function push(array &$state, array $point, CarbonImmutable $at, float $heading, bool $ignition, bool $motion, float $step, float $kmh = 0.0): void
    {
        $recordedAt = $at->format('Y-m-d H:i:s');

        if ($state['last'] !== null && $state['last']['recorded_at'] >= $recordedAt) {
            return;
        }

        $faker = $this->ctx->faker;
        $entry = $state['entry'];
        $heavy = $entry['usage'] === 'heavy';
        $state['total'] += $step;
        $state['odometer'] += $step;

        if ($ignition) {
            $state['hours'] += self::SAMPLE_SECONDS * 1000;
        }

        $power = $heavy
            ? ($ignition ? $faker->randomFloat(2, 27.2, 28.4) : $faker->randomFloat(2, 24.6, 25.4))
            : ($ignition ? $faker->randomFloat(2, 13.6, 14.3) : $faker->randomFloat(2, 12.3, 12.8));
        $batteryLevel = $faker->numberBetween(78, 100);

        $row = [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->ctx->tenant->getKey(),
            'vehicle_id' => $entry['vehicle']->getKey(),
            'client_id' => $entry['client']->getKey(),
            'equipment_id' => $entry['equipment']->getKey(),
            'latitude' => round($point[0], 7),
            'longitude' => round($point[1], 7),
            'recorded_at' => $recordedAt,
            'server_time' => $at->addSeconds($faker->numberBetween(1, 4))->format('Y-m-d H:i:s'),
            'speed' => round($kmh / self::KNOTS, 2),
            'ignition' => $ignition,
            'battery' => $batteryLevel,
            'heading' => round($heading, 2),
            'altitude' => $faker->numberBetween(880, 960),
            'address' => null,
            'valid' => true,
            'attributes' => json_encode([
                'ignition' => $ignition,
                'motion' => $motion,
                'sat' => $faker->numberBetween(7, 15),
                'rssi' => $faker->numberBetween(2, 5),
                'power' => $power,
                'batteryLevel' => $batteryLevel,
                'distance' => round($step, 2),
                'totalDistance' => round($state['total'], 2),
                'odometer' => (int) round($state['odometer']),
                'hours' => $state['hours'],
                'protocol' => $entry['protocol'],
            ]),
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
        ];

        $state['last'] = ['recorded_at' => $recordedAt, 'heading' => $heading, 'lat' => $row['latitude'], 'lng' => $row['longitude'], 'at' => $at];
        $state['positions'][] = $state['last'];
        $this->positionRows[] = $row;

        if (count($this->positionRows) >= 2000) {
            $this->flushPositions();
        }
    }

    private function flushPositions(): void
    {
        if ($this->positionRows !== []) {
            DB::table('gps_positions')->insert($this->positionRows);
            $this->positionRows = [];
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array{0: float, 1: float}  $point
     */
    private function geofenceEvent(array &$state, GeofenceEventType $type, array $point, CarbonImmutable $at, float $kmh): void
    {
        $entry = $state['entry'];
        $geofence = $this->baseGeofences[$entry['client']->getKey()];
        $vehicle = $entry['vehicle'];
        $state['inside'] = $type === GeofenceEventType::ENTRY;
        $state['last_geofence_at'] = $at->format('Y-m-d H:i:s');

        $this->geofenceEventRows[] = [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->ctx->tenant->getKey(),
            'client_id' => $entry['client']->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'geofence_id' => $geofence->getKey(),
            'gps_position_id' => null,
            'type' => $type->value,
            'latitude' => round($point[0], 7),
            'longitude' => round($point[1], 7),
            'recorded_at' => $at->format('Y-m-d H:i:s'),
            'processed_at' => $at->addSeconds(3)->format('Y-m-d H:i:s'),
            'speed' => round($kmh / self::KNOTS, 2),
            'meta' => null,
            'created_at' => $at->format('Y-m-d H:i:s'),
            'updated_at' => $at->format('Y-m-d H:i:s'),
        ];

        $entryEvent = $type === GeofenceEventType::ENTRY;

        $this->alert($entry, AlertType::GEOFENCE, AlertSeverity::MEDIUM, $entryEvent
            ? "Entrada na geocerca {$geofence->name}"
            : "Saída da geocerca {$geofence->name}", $entryEvent
            ? "Veículo {$vehicle->plate} entrou na geocerca {$geofence->name}."
            : "Veículo {$vehicle->plate} saiu da geocerca {$geofence->name}.", $at, ['lat' => $point[0], 'lng' => $point[1]], [
                'geofence_id' => $geofence->uuid,
                'event_type' => $type->value,
            ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array{at: CarbonImmutable, kmh: float, point: array{0: float, 1: float}}>  $samples
     */
    private function speedAlert(array $state, array $samples, int $limit): void
    {
        $first = $samples[0];
        $last = $samples[array_key_last($samples)];
        $max = max(array_column($samples, 'kmh'));
        $avg = array_sum(array_column($samples, 'kmh')) / count($samples);
        $seconds = (int) $first['at']->diffInSeconds($last['at']);

        $this->alert($state['entry'], AlertType::SPEED, AlertSeverity::MEDIUM, 'Excesso de Velocidade', 'Veículo excedeu o limite configurado de velocidade.', $first['at'], ['lat' => $first['point'][0], 'lng' => $first['point'][1]], [
            'speed_limit_kmh' => $limit,
            'max_speed_kmh' => round($max, 1),
            'avg_speed_kmh' => round($avg, 1),
            'duration_seconds' => $seconds,
            'started_at' => $first['at']->toIso8601String(),
            'ended_at' => $last['at']->toIso8601String(),
            'distance_meters' => (int) round($avg / 3.6 * $seconds),
            'position_count' => count($samples),
            'start_latitude' => round($first['point'][0], 7),
            'start_longitude' => round($first['point'][1], 7),
            'end_latitude' => round($last['point'][0], 7),
            'end_longitude' => round($last['point'][1], 7),
        ], $max / self::KNOTS);
    }

    /**
     * Alertas de comunicação e bateria do veículo.
     *
     * @param  array<string, mixed>  $state
     */
    private function vehicleAlerts(array $state, CarbonImmutable $end): void
    {
        $entry = $state['entry'];
        $last = $state['last'];

        if ($last === null) {
            return;
        }

        if ($entry['signal'] !== 'online') {
            $lastAt = $last['at']->setTimezone(DemoContext::TIMEZONE)->format('d/m/Y H:i');

            $this->alert($entry, AlertType::OFFLINE, AlertSeverity::HIGH, 'Dispositivo offline', "Veículo {$entry['vehicle']->plate} sem comunicação há mais de 15 minutos (última posição {$lastAt}).", $last['at']->addMinutes(15), ['lat' => $last['lat'], 'lng' => $last['lng']], [
                'offline_minutes' => 15,
                'last_position_at' => $last['at']->toIso8601String(),
            ], null, $entry['signal'] === 'offline_day' ? AlertStatus::ACKNOWLEDGED : AlertStatus::OPEN);

            $this->offlineState($entry, $last['at']);
        }

        if ((int) $entry['vehicle']->vehicle_type === 2 && $this->ctx->faker->boolean(40)) {
            $at = $end->subHours($this->ctx->faker->numberBetween(3, 60));
            $this->alert($entry, AlertType::BATTERY, AlertSeverity::MEDIUM, 'Bateria baixa', 'Nível de bateria do rastreador abaixo de 20%.', $at, $this->positionAt($state, $at), [
                'battery_level' => $this->ctx->faker->numberBetween(9, 19),
                'battery_threshold' => 20,
            ]);
        }
    }

    /**
     * Marca o estado de offline como ativo para o scheduler não repetir o
     * alerta já registrado.
     *
     * @param  array<string, mixed>  $entry
     */
    private function offlineState(array $entry, CarbonImmutable $lastAt): void
    {
        $configId = AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('vehicle_id', $entry['vehicle']->getKey())
            ->where('type', AlertType::OFFLINE->value)
            ->value('id');

        if ($configId === null) {
            return;
        }

        DB::table('alert_states')->insert([
            'tenant_id' => $this->ctx->tenant->getKey(),
            'vehicle_id' => $entry['vehicle']->getKey(),
            'alert_config_id' => $configId,
            'type' => AlertType::OFFLINE->value,
            'is_active' => true,
            'started_at' => $lastAt->addMinutes(15)->format('Y-m-d H:i:s'),
            'last_recorded_at' => $lastAt->format('Y-m-d H:i:s'),
            'created_at' => $lastAt->addMinutes(15)->format('Y-m-d H:i:s'),
            'updated_at' => $lastAt->addMinutes(15)->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Ocorrências pontuais para a demonstração: SOS, jamming e alarmes do
     * dispositivo.
     */
    private function seedSpecialAlerts(): void
    {
        $ctx = $this->ctx;
        $online = array_values(array_filter($ctx->fleet, fn ($entry) => $entry['signal'] === 'online' && $entry['client']->is_active));
        $pick = fn (string $usage) => collect($online)->first(fn ($entry) => $entry['usage'] === $usage) ?? $online[0];

        $truck = $pick('heavy');
        $this->alert($truck, AlertType::JAMMING, AlertSeverity::CRITICAL, 'Jamming detectado', "Possível bloqueador de sinal (jammer) no veículo {$truck['vehicle']->plate}.", $ctx->now->subMinutes(38), $this->lastPosition($truck), ['alarm' => 'jamming'], null, AlertStatus::OPEN);

        $moto = collect($online)->first(fn ($entry) => (int) $entry['vehicle']->vehicle_type === 2) ?? $pick('fleet');
        $this->alert($moto, AlertType::SOS, AlertSeverity::CRITICAL, 'SOS acionado', 'Botão de pânico acionado pelo condutor.', $ctx->now->subDays(3)->setTime(19, 42), $this->lastPosition($moto), ['alarm' => 'sos'], null, AlertStatus::RESOLVED);

        $van = $pick('fleet');
        $this->alert($van, AlertType::SOS, AlertSeverity::CRITICAL, 'SOS acionado', 'Botão de pânico acionado pelo condutor.', $ctx->now->subHours(2), $this->lastPosition($van), ['alarm' => 'sos'], null, AlertStatus::ACKNOWLEDGED);

        foreach ([['gpsantennacut', 'Antena GPS cortada', AlertSeverity::HIGH], ['hardbraking', 'Frenagem brusca', AlertSeverity::MEDIUM], ['hardacceleration', 'Aceleração brusca', AlertSeverity::LOW], ['poweroff', 'Dispositivo desligado', AlertSeverity::HIGH]] as $i => [$code, $label, $severity]) {
            $entry = $online[($i * 7 + 3) % count($online)];
            $at = $ctx->now->subHours(1 + $i * 9);
            $this->alert($entry, AlertType::DEVICE_ALARM, $severity, $label, "Alarme \"{$label}\" recebido do rastreador.", $at, $this->lastPosition($entry), ['alarm' => $code]);
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{lat: float, lng: float}  $point
     * @param  array<string, mixed>  $meta
     */
    private function alert(
        array $entry,
        AlertType $type,
        AlertSeverity $severity,
        string $title,
        string $description,
        CarbonImmutable $at,
        array $point,
        array $meta,
        ?float $speedKnots = null,
        ?AlertStatus $status = null,
    ): void {
        $faker = $this->ctx->faker;
        $ageHours = $at->diffInHours($this->ctx->now);
        $status ??= match (true) {
            $ageHours > 12 => AlertStatus::RESOLVED,
            $ageHours > 2 => $faker->boolean(60) ? AlertStatus::ACKNOWLEDGED : AlertStatus::OPEN,
            default => AlertStatus::OPEN,
        };
        $operator = $faker->randomElement([$this->ctx->staff[1], $this->ctx->staff[2]]);
        $acknowledgedAt = $status !== AlertStatus::OPEN ? $at->addMinutes($faker->numberBetween(2, 40)) : null;
        $resolvedAt = $status === AlertStatus::RESOLVED ? $acknowledgedAt->addMinutes($faker->numberBetween(5, 180)) : null;

        $this->alertRows[] = [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->ctx->tenant->getKey(),
            'client_id' => $entry['client']->getKey(),
            'vehicle_id' => $entry['vehicle']->getKey(),
            'equipment_id' => $entry['equipment']?->getKey(),
            'gps_position_id' => null,
            'type' => $type->value,
            'severity' => $severity->value,
            'status' => $status->value,
            'title' => $title,
            'description' => $description,
            'latitude' => round($point['lat'], 7),
            'longitude' => round($point['lng'], 7),
            'speed' => $speedKnots !== null ? round($speedKnots, 2) : null,
            'meta' => json_encode($meta),
            'occurred_at' => $at->format('Y-m-d H:i:s'),
            'acknowledged_at' => $acknowledgedAt?->format('Y-m-d H:i:s'),
            'resolved_at' => $resolvedAt?->format('Y-m-d H:i:s'),
            'acknowledged_by' => $acknowledgedAt ? $operator->getKey() : null,
            'created_at' => $at->format('Y-m-d H:i:s'),
            'updated_at' => ($resolvedAt ?? $acknowledgedAt ?? $at)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Comandos enviados pela central nas últimas semanas.
     */
    private function seedCommands(): void
    {
        $ctx = $this->ctx;
        $faker = $ctx->faker;
        $fleet = array_values(array_filter($ctx->fleet, fn ($entry) => $entry['equipment'] !== null && $entry['client']->is_active));

        for ($i = 0; $i < 30; $i++) {
            $entry = $faker->randomElement($fleet);
            $command = $faker->randomElement(['engineStop', 'engineResume', 'positionSingle', 'positionSingle', 'rebootDevice']);
            $requestedAt = $ctx->now->subMinutes($faker->numberBetween(30, 20 * 1440));
            $status = match (true) {
                $i === 0 => DeviceCommandStatus::PENDING,
                $entry['signal'] !== 'online' && $faker->boolean(60) => DeviceCommandStatus::FAILED,
                $faker->boolean(6) => DeviceCommandStatus::FAILED,
                default => DeviceCommandStatus::SUCCESS,
            };

            if ($i === 0) {
                $requestedAt = $ctx->now->subMinutes(2);
            }

            DeviceCommandLog::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'vehicle_id' => $entry['vehicle']->getKey(),
                'equipment_id' => $entry['equipment']->getKey(),
                'user_id' => $faker->randomElement([$ctx->staff[0], $ctx->staff[1], $ctx->staff[2]])->getKey(),
                'command_type' => $command,
                'payload' => ['type' => $command, 'attributes' => []],
                'status' => $status,
                'requested_at' => $requestedAt,
                'executed_at' => $status === DeviceCommandStatus::PENDING ? null : $requestedAt->addSeconds($faker->numberBetween(2, 9)),
                'response' => match ($status) {
                    DeviceCommandStatus::SUCCESS => ['http_status' => 200, 'body' => ['result' => 'Command sent']],
                    DeviceCommandStatus::FAILED => ['http_status' => 400, 'body' => ['error' => 'Device is offline']],
                    default => null,
                },
                'created_at' => $requestedAt,
            ]);
        }
    }

    /**
     * Notificações da central (alertas recentes), dos clientes (alertas
     * dos seus veículos, avisos e cobranças) e dos técnicos (agenda).
     */
    private function seedNotifications(): void
    {
        $ctx = $this->ctx;
        $faker = $ctx->faker;
        $rows = [];
        $staff = [$ctx->staff[0], $ctx->staff[1], $ctx->staff[2]];
        $clientUsers = collect($ctx->clients)->mapWithKeys(fn ($entry) => [$entry['client']->getKey() => $entry['users']]);
        $plates = collect($ctx->fleet)->mapWithKeys(fn ($entry) => [$entry['vehicle']->getKey() => $entry['vehicle']]);

        $recent = DB::table('alerts')
            ->where('tenant_id', $ctx->tenant->getKey())
            ->where('occurred_at', '>=', $ctx->now->subHours(36))
            ->orderBy('occurred_at')
            ->get();

        foreach ($recent as $alert) {
            $relevant = in_array($alert->severity, ['high', 'critical'], true) || $alert->type === AlertType::SPEED->value;
            $vehicle = $plates->get($alert->vehicle_id);
            $occurred = CarbonImmutable::parse($alert->occurred_at);
            $data = [
                'alert_id' => $alert->uuid,
                'vehicle_id' => $vehicle?->uuid,
                'plate' => $vehicle?->plate,
                'severity' => $alert->severity,
                'latitude' => (float) $alert->latitude,
                'longitude' => (float) $alert->longitude,
            ];

            $recipients = $relevant ? $staff : [];

            if ($relevant || $alert->type === AlertType::GEOFENCE->value) {
                $recipients = [...$recipients, ...($clientUsers->get($alert->client_id) ?? [])];
            }

            foreach ($recipients as $user) {
                $rows[] = $this->notificationRow($user->getKey(), $alert->type, NotificationSource::ALERT, $alert->title, $alert->description, $data, $occurred, $occurred->lessThan($ctx->now->subHours(3)) && $faker->boolean(75), (int) $alert->id);
            }
        }

        $maintenance = $ctx->now->subDays(5)->setTime(13, 0);

        foreach ($ctx->clients as $entry) {
            foreach ($entry['users'] as $user) {
                $rows[] = $this->notificationRow($user->getKey(), 'manual', NotificationSource::MANUAL, 'Manutenção programada da plataforma', 'No domingo, das 02h às 04h, o aplicativo pode ficar instável por manutenção programada. O rastreamento não será interrompido.', [], $maintenance, $faker->boolean(60), null, $ctx->admin->getKey());
            }

            if (in_array($entry['profile'], ['overdue', 'suspended'], true)) {
                foreach ($entry['users'] as $user) {
                    $rows[] = $this->notificationRow($user->getKey(), 'finance.overdue', NotificationSource::FINANCE, 'Fatura em atraso', 'Identificamos uma fatura vencida. Regularize para evitar a suspensão do serviço.', [], $ctx->now->subDays(2)->setTime(12, 0), false);
                }
            }
        }

        $orders = DB::table('service_orders')
            ->where('tenant_id', $ctx->tenant->getKey())
            ->whereNotNull('technician_id')
            ->where('status', 'open')
            ->get(['uuid', 'number', 'technician_id', 'scheduled_start_at', 'created_at']);

        foreach ($orders as $order) {
            $start = CarbonImmutable::parse($order->scheduled_start_at)->setTimezone(DemoContext::TIMEZONE);
            $rows[] = $this->notificationRow((int) $order->technician_id, 'service_order.assigned', NotificationSource::SERVICE_ORDER, 'Nova OS atribuída', sprintf('OS %d foi atribuída a você para %s às %s.', $order->number, $start->format('d/m'), $start->format('H:i')), [
                'service_order_id' => $order->uuid,
            ], CarbonImmutable::parse($order->created_at)->addMinutes(2), $faker->boolean(50));
        }

        $this->insertChunked('user_notifications', $rows);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function notificationRow(int $userId, string $type, NotificationSource $source, string $title, ?string $body, array $data, CarbonImmutable $at, bool $read, ?int $alertId = null, ?int $createdBy = null): array
    {
        $readAt = $read ? $at->addMinutes($this->ctx->faker->numberBetween(1, 120)) : null;

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->ctx->tenant->getKey(),
            'user_id' => $userId,
            'alert_id' => $alertId,
            'type' => $type,
            'source' => $source->value,
            'created_by' => $createdBy,
            'title' => $title,
            'body' => $body,
            'data' => json_encode($data),
            'read_at' => $readAt?->format('Y-m-d H:i:s'),
            'clicked_at' => $read && $this->ctx->faker->boolean(40) ? $readAt->format('Y-m-d H:i:s') : null,
            'created_at' => $at->format('Y-m-d H:i:s'),
            'updated_at' => ($readAt ?? $at)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{lat: float, lng: float}
     */
    private function positionAt(array $state, CarbonImmutable $at): array
    {
        $match = null;

        foreach ($state['positions'] as $position) {
            if ($position['at']->greaterThan($at)) {
                break;
            }

            $match = $position;
        }

        $match ??= $state['positions'][0];

        return ['lat' => $match['lat'], 'lng' => $match['lng']];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{lat: float, lng: float}
     */
    private function lastPosition(array $entry): array
    {
        $row = DB::table('gps_positions')
            ->where('vehicle_id', $entry['vehicle']->getKey())
            ->orderByDesc('recorded_at')
            ->first(['latitude', 'longitude']);

        return $row !== null
            ? ['lat' => (float) $row->latitude, 'lng' => (float) $row->longitude]
            : $this->ctx->place($entry['base']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function distance(array $a, array $b): float
    {
        $lat1 = deg2rad($a[0]);
        $lat2 = deg2rad($b[0]);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * 6_371_000 * asin(min(1.0, sqrt($h)));
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function bearing(array $a, array $b): float
    {
        $lat1 = deg2rad($a[0]);
        $lat2 = deg2rad($b[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $y = sin($dLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }
}
