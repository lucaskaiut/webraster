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
 * Tempo e distância de deslocamento via Google Routes ou OSRM, com estimativa
 * em linha reta como último fallback.
 *
 * No Google usamos a preferência TRAFFIC_UNAWARE: o cálculo ignora
 * completamente as condições de trânsito e considera apenas a malha viária.
 */
class RouteEstimator
{
    public function estimate(Coordinates $from, Coordinates $to): TravelEstimate
    {
        if (! config('scheduling.routing.enabled')) {
            return $this->fallback($from, $to);
        }

        $provider = $this->provider();
        $key = sprintf(
            'scheduling:route:v4:%s:%.5f,%.5f:%.5f,%.5f',
            $provider,
            $from->latitude,
            $from->longitude,
            $to->latitude,
            $to->longitude,
        );

        /** @var array{minutes: int, distance_meters: int, source?: string, geometry?: list<array{0: float, 1: float}>}|null $cached */
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return new TravelEstimate(
                $cached['minutes'],
                $cached['distance_meters'],
                $cached['source'] ?? ($provider === 'google' ? TravelEstimate::SOURCE_GOOGLE : TravelEstimate::SOURCE_OSRM),
                $cached['geometry'] ?? [],
            );
        }

        $estimate = $provider === 'google'
            ? $this->requestGoogle($from, $to)
            : $this->request($from, $to);

        // A rota do Google é preferencial, mas uma indisponibilidade não pode
        // travar o agendamento: o OSRM assume até nova tentativa.
        if ($estimate === null && $provider === 'google') {
            $estimate = $this->request($from, $to);
        }

        if ($estimate === null) {
            return $this->fallback($from, $to);
        }

        Cache::put(
            $key,
            [
                'minutes' => $estimate->minutes,
                'distance_meters' => $estimate->distanceMeters,
                'source' => $estimate->source,
                'geometry' => $estimate->geometry,
            ],
            now()->addHours((int) config('scheduling.routing.cache_hours', 24)),
        );

        return $estimate;
    }

    private function provider(): string
    {
        $configured = mb_strtolower(trim((string) config('scheduling.routing.provider', 'google')));

        return $configured === 'google' && $this->googleApiKey() !== null ? 'google' : 'osrm';
    }

    private function googleApiKey(): ?string
    {
        $key = trim((string) config('scheduling.google.api_key'));

        return $key === '' ? null : $key;
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

    private function requestGoogle(Coordinates $from, Coordinates $to): ?TravelEstimate
    {
        $key = $this->googleApiKey();

        if ($key === null) {
            return null;
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('scheduling.google.routes_url'), '/'))
                ->withHeaders([
                    'X-Goog-Api-Key' => $key,
                    'X-Goog-FieldMask' => implode(',', [
                        'routes.duration',
                        'routes.distanceMeters',
                        'routes.polyline.encodedPolyline',
                    ]),
                ])
                ->timeout((int) config('scheduling.google.timeout', 8))
                ->acceptJson()
                ->post('/directions/v2:computeRoutes', [
                    'origin' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => $from->latitude,
                                'longitude' => $from->longitude,
                            ],
                        ],
                    ],
                    'destination' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => $to->latitude,
                                'longitude' => $to->longitude,
                            ],
                        ],
                    ],
                    'travelMode' => 'DRIVE',
                    // Ignora as condições de trânsito por completo.
                    'routingPreference' => 'TRAFFIC_UNAWARE',
                    'computeAlternativeRoutes' => false,
                    'polylineQuality' => 'OVERVIEW',
                    'languageCode' => (string) config('scheduling.google.language', 'pt-BR'),
                    'units' => 'METRIC',
                ]);

            if ($response->failed()) {
                Log::warning('Google Routes falhou', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $duration = $response->json('routes.0.duration');
            $distance = $response->json('routes.0.distanceMeters');

            if (! is_numeric(rtrim((string) $duration, 's')) || ! is_numeric($distance)) {
                return null;
            }

            $encoded = $response->json('routes.0.polyline.encodedPolyline');
            $geometry = is_string($encoded) ? $this->decodePolyline($encoded) : null;

            return new TravelEstimate(
                TimeGrid::minutes((int) ceil(((float) rtrim((string) $duration, 's')) / 60)),
                (int) round((float) $distance),
                TravelEstimate::SOURCE_GOOGLE,
                $geometry ?? [
                    [$from->latitude, $from->longitude],
                    [$to->latitude, $to->longitude],
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Google Routes indisponível', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Decodifica a polyline compacta do Google para [latitude, longitude].
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private function decodePolyline(string $encoded): ?array
    {
        if ($encoded === '') {
            return null;
        }

        $points = [];
        $index = 0;
        $latitude = 0;
        $longitude = 0;
        $length = strlen($encoded);

        while ($index < $length) {
            $shift = 0;
            $result = 0;

            do {
                if ($index >= $length) {
                    return null;
                }

                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);

            $latitude += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $shift = 0;
            $result = 0;

            do {
                if ($index >= $length) {
                    return null;
                }

                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);

            $longitude += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $points[] = [$latitude / 1e5, $longitude / 1e5];
        }

        return $points === [] ? null : $points;
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
