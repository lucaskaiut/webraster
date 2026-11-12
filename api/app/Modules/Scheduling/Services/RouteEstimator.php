<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Geofence\Support\GeoMath;
use App\Modules\Scheduling\Support\Coordinates;
use App\Modules\Scheduling\Support\TimeGrid;
use App\Modules\Scheduling\Support\TravelEstimate;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tempo e distância de deslocamento via Valhalla (servidor próprio), Google
 * Routes ou OSRM, com estimativa em linha reta como último fallback.
 *
 * No Google usamos a preferência TRAFFIC_UNAWARE: o cálculo ignora
 * completamente as condições de trânsito e considera apenas a malha viária.
 * O Valhalla também calcula sem trânsito.
 */
class RouteEstimator
{
    /** Limite de rotas guardadas em memória (comandos longos não crescem sem fim). */
    private const MEMO_LIMIT = 1000;

    /**
     * Rotas já resolvidas nesta requisição/job. A sugestão de horários consulta
     * os mesmos pares centenas de vezes; com isso cada par sai no máximo uma
     * vez para o provedor, mesmo com o cache persistente vazio ou indisponível.
     *
     * @var array<string, TravelEstimate>
     */
    private array $memo = [];

    public function estimate(Coordinates $from, Coordinates $to): TravelEstimate
    {
        if (! config('scheduling.routing.enabled')) {
            return $this->fallback($from, $to);
        }

        $provider = $this->provider();
        $key = $this->cacheKey($provider, $from, $to);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        return $this->remember($key, $this->resolve($provider, $key, $from, $to));
    }

    private function remember(string $key, TravelEstimate $estimate): TravelEstimate
    {
        if (count($this->memo) >= self::MEMO_LIMIT) {
            $this->memo = [];
        }

        return $this->memo[$key] = $estimate;
    }

    private function resolve(string $provider, string $key, Coordinates $from, Coordinates $to): TravelEstimate
    {
        /** @var array{minutes: int, distance_meters: int, source?: string, geometry?: list<array{0: float, 1: float}>}|null $cached */
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return new TravelEstimate(
                $cached['minutes'],
                $cached['distance_meters'],
                $cached['source'] ?? $this->sourceFor($provider),
                $cached['geometry'] ?? [],
            );
        }

        $estimate = match ($provider) {
            'valhalla' => $this->requestValhalla($from, $to),
            'google' => $this->requestGoogle($from, $to),
            default => $this->request($from, $to),
        };

        // Uma indisponibilidade do provedor principal não pode travar o
        // agendamento: a reserva assume até nova tentativa.
        if ($estimate === null && $provider === 'valhalla' && $this->valhallaFallback() === 'google') {
            $estimate = $this->requestGoogle($from, $to);
        }

        if ($estimate === null && $provider !== 'osrm') {
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
            $this->cacheExpiration($provider, $estimate),
        );

        return $estimate;
    }

    /**
     * Coordenadas arredondadas (padrão: 4 casas ≈ 11 m) para que pequenas
     * variações do mesmo endereço reaproveitem a rota já calculada.
     */
    private function cacheKey(string $provider, Coordinates $from, Coordinates $to): string
    {
        $precision = max(0, min(6, (int) config('scheduling.routing.cache_precision', 4)));
        $format = '%.'.$precision.'F';

        return sprintf(
            'scheduling:route:v5:%s:%s,%s:%s,%s',
            $provider,
            sprintf($format, round($from->latitude, $precision)),
            sprintf($format, round($from->longitude, $precision)),
            sprintf($format, round($to->latitude, $precision)),
            sprintf($format, round($to->longitude, $precision)),
        );
    }

    private function cacheExpiration(string $provider, TravelEstimate $estimate): DateTimeInterface
    {
        if ($provider !== 'osrm' && $estimate->source !== $this->sourceFor($provider)) {
            return now()->addMinutes((int) config('scheduling.routing.fallback_cache_minutes', 60));
        }

        return now()->addHours((int) config('scheduling.routing.cache_hours', 720));
    }

    /**
     * Provedor principal. Sem credencial, cai para o próximo disponível
     * (Valhalla → Google → OSRM).
     */
    private function provider(): string
    {
        $configured = mb_strtolower(trim((string) config('scheduling.routing.provider', 'google')));

        if ($configured === 'valhalla' && $this->valhallaToken() !== null) {
            return 'valhalla';
        }

        return in_array($configured, ['valhalla', 'google'], true) && $this->googleApiKey() !== null ? 'google' : 'osrm';
    }

    private function sourceFor(string $provider): string
    {
        return match ($provider) {
            'valhalla' => TravelEstimate::SOURCE_VALHALLA,
            'google' => TravelEstimate::SOURCE_GOOGLE,
            default => TravelEstimate::SOURCE_OSRM,
        };
    }

    private function valhallaToken(): ?string
    {
        $token = trim((string) config('scheduling.valhalla.token'));

        return $token === '' ? null : $token;
    }

    private function valhallaFallback(): string
    {
        return mb_strtolower(trim((string) config('scheduling.valhalla.fallback', 'google'))) === 'osrm' ? 'osrm' : 'google';
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

    /**
     * Rota de carro no Valhalla (via proxy do Nuvemax Rotas), sem trânsito.
     */
    private function requestValhalla(Coordinates $from, Coordinates $to): ?TravelEstimate
    {
        $token = $this->valhallaToken();

        if ($token === null) {
            return null;
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('scheduling.valhalla.url'), '/'))
                ->withToken($token)
                ->timeout((int) config('scheduling.valhalla.timeout', 8))
                ->acceptJson()
                ->post('/v1/route', [
                    'locations' => [
                        ['lat' => $from->latitude, 'lon' => $from->longitude],
                        ['lat' => $to->latitude, 'lon' => $to->longitude],
                    ],
                    'costing' => 'auto',
                    'directions_type' => 'none',
                    'units' => 'kilometers',
                ]);

            if ($response->failed()) {
                Log::warning('Valhalla falhou', [
                    'status' => $response->status(),
                    'error' => $response->json('error'),
                ]);

                return null;
            }

            $seconds = $response->json('trip.summary.time');
            $kilometers = $response->json('trip.summary.length');

            if (! is_numeric($seconds) || ! is_numeric($kilometers)) {
                return null;
            }

            $shape = $response->json('trip.legs.0.shape');
            $geometry = is_string($shape) ? $this->decodePolyline($shape, 6) : null;

            return new TravelEstimate(
                TimeGrid::minutes((int) ceil(((float) $seconds) / 60)),
                (int) round(((float) $kilometers) * 1000),
                TravelEstimate::SOURCE_VALHALLA,
                $geometry ?? [
                    [$from->latitude, $from->longitude],
                    [$to->latitude, $to->longitude],
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Valhalla indisponível', ['error' => $exception->getMessage()]);

            return null;
        }
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
     * Decodifica uma polyline compacta para [latitude, longitude]. O Google
     * usa precisão 5 e o Valhalla, 6.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private function decodePolyline(string $encoded, int $precision = 5): ?array
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

            $points[] = [$latitude / 10 ** $precision, $longitude / 10 ** $precision];
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
