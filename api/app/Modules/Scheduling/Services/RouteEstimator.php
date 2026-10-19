<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Geofence\Support\GeoMath;
use App\Modules\Scheduling\Support\Coordinates;
use App\Modules\Scheduling\Support\TimeGrid;
use App\Modules\Scheduling\Support\TravelEstimate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tempo e distância de deslocamento via OSRM, com estimativa em linha reta como fallback.
 */
class RouteEstimator
{
    public function estimate(Coordinates $from, Coordinates $to): TravelEstimate
    {
        if (! config('scheduling.routing.enabled')) {
            return $this->fallback($from, $to);
        }

        $key = sprintf(
            'scheduling:route:v3:%.5f,%.5f:%.5f,%.5f',
            $from->latitude,
            $from->longitude,
            $to->latitude,
            $to->longitude,
        );

        /** @var array{minutes: int, distance_meters: int, geometry?: list<array{0: float, 1: float}>}|null $cached */
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return new TravelEstimate(
                $cached['minutes'],
                $cached['distance_meters'],
                TravelEstimate::SOURCE_OSRM,
                $cached['geometry'] ?? [],
            );
        }

        $estimate = $this->request($from, $to);

        if ($estimate === null) {
            return $this->fallback($from, $to);
        }

        Cache::put(
            $key,
            [
                'minutes' => $estimate->minutes,
                'distance_meters' => $estimate->distanceMeters,
                'geometry' => $estimate->geometry,
            ],
            now()->addHours((int) config('scheduling.routing.cache_hours', 24)),
        );

        return $estimate;
    }

    public function fallback(Coordinates $from, Coordinates $to): TravelEstimate
    {
        $straight = GeoMath::haversineMeters($from->latitude, $from->longitude, $to->latitude, $to->longitude);
        $meters = $straight * (float) config('scheduling.routing.fallback_road_factor', 1.3);
        $speedMetersPerMinute = max(1.0, (float) config('scheduling.routing.fallback_speed_kmh', 35)) * 1000 / 60;

        return new TravelEstimate(
            TimeGrid::minutes((int) ceil($meters / $speedMetersPerMinute)),
            (int) round($meters),
            TravelEstimate::SOURCE_ESTIMATE,
            [
                [$from->latitude, $from->longitude],
                [$to->latitude, $to->longitude],
            ],
        );
    }

    private function request(Coordinates $from, Coordinates $to): ?TravelEstimate
    {
        try {
            $path = sprintf(
                '/route/v1/%s/%F,%F;%F,%F',
                config('scheduling.routing.profile', 'driving'),
                $from->longitude,
                $from->latitude,
                $to->longitude,
                $to->latitude,
            );

            $response = Http::baseUrl(rtrim((string) config('scheduling.routing.url'), '/'))
                ->timeout((int) config('scheduling.routing.timeout', 8))
                ->acceptJson()
                ->get($path, [
                    'overview' => 'simplified',
                    'geometries' => 'geojson',
                ]);

            if ($response->failed() || $response->json('code') !== 'Ok') {
                Log::warning('OSRM falhou', ['status' => $response->status(), 'code' => $response->json('code')]);

                return null;
            }

            $duration = $response->json('routes.0.duration');
            $distance = $response->json('routes.0.distance');

            if (! is_numeric($duration) || ! is_numeric($distance)) {
                return null;
            }

            return new TravelEstimate(
                TimeGrid::minutes((int) ceil(((float) $duration) / 60)),
                (int) round((float) $distance),
                TravelEstimate::SOURCE_OSRM,
                $this->geometry($response->json('routes.0.geometry.coordinates'))
                    ?? [
                        [$from->latitude, $from->longitude],
                        [$to->latitude, $to->longitude],
                    ],
            );
        } catch (Throwable $exception) {
            Log::warning('OSRM indisponível', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Converte a geometria GeoJSON do OSRM ([longitude, latitude]) para
     * [latitude, longitude], formato usado pelos mapas do painel.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private function geometry(mixed $coordinates): ?array
    {
        if (! is_array($coordinates)) {
            return null;
        }

        $points = [];

        foreach ($coordinates as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }

            [$longitude, $latitude] = $point;

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                continue;
            }

            $points[] = [(float) $latitude, (float) $longitude];
        }

        return $points === [] ? null : $points;
    }
}
