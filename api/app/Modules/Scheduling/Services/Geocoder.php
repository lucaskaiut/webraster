<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Support\Coordinates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Converte endereços em coordenadas via Nominatim (OpenStreetMap).
 */
class Geocoder
{
    public function geocode(?string $address): ?Coordinates
    {
        $address = trim((string) $address);

        if ($address === '' || ! config('scheduling.geocoding.enabled')) {
            return null;
        }

        $key = 'scheduling:geocode:'.md5(mb_strtolower($address));

        /** @var array{0: float, 1: float}|false|null $cached */
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return new Coordinates($cached[0], $cached[1]);
        }

        if ($cached === false) {
            return null;
        }

        $coordinates = $this->request($address);

        // Endereços não encontrados também vão para o cache, para não repetir a consulta a cada estimativa.
        Cache::put(
            $key,
            $coordinates ? [$coordinates->latitude, $coordinates->longitude] : false,
            now()->addDays($coordinates ? (int) config('scheduling.geocoding.cache_days', 30) : 1),
        );

        return $coordinates;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function formatAddress(array $parts): string
    {
        $street = trim(implode(', ', array_filter([
            $parts['street'] ?? null,
            $parts['number'] ?? null,
        ], fn ($value) => filled($value))));

        return implode(' - ', array_filter([
            $street,
            $parts['neighborhood'] ?? null,
            trim(implode('/', array_filter([$parts['city'] ?? null, $parts['state'] ?? null], fn ($value) => filled($value)))),
            $parts['zip'] ?? null,
        ], fn ($value) => filled($value)));
    }

    /**
     * Sugestões de endereço para autocomplete (Nominatim).
     *
     * @return list<array{title: string, subtitle: string|null, address: string, latitude: float, longitude: float}>
     */
    public function search(string $query, int $limit = 6): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3 || ! config('scheduling.geocoding.enabled')) {
            return [];
        }

        $limit = max(1, min($limit, 8));
        $key = 'scheduling:geocode:search:'.md5(mb_strtolower($query)).':'.$limit;

        /** @var list<array<string, mixed>>|null $cached */
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $results = $this->requestSearch($query, $limit);

        Cache::put(
            $key,
            $results,
            $results === [] ? now()->addMinute() : now()->addMinutes(10),
        );

        return $results;
    }

    /**
     * @return list<array{title: string, subtitle: string|null, address: string, latitude: float, longitude: float}>
     */
    private function requestSearch(string $query, int $limit): array
    {
        try {
            // O Nominatim aceita no máximo 1 requisição por segundo. O lock
            // expira sozinho, então chamadas próximas aguardam a vez.
            $lock = Cache::lock('scheduling:geocoding:search', 1);

            if (! $lock->block(2)) {
                return [];
            }

            $response = Http::baseUrl(rtrim((string) config('scheduling.geocoding.url'), '/'))
                ->withHeaders(['User-Agent' => (string) config('scheduling.geocoding.user_agent')])
                ->timeout((int) config('scheduling.geocoding.timeout', 8))
                ->acceptJson()
                ->get('/search', array_filter([
                    'q' => $query,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'dedupe' => 1,
                    'limit' => $limit,
                    'countrycodes' => config('scheduling.geocoding.country_codes'),
                    'accept-language' => 'pt-BR',
                ]));

            if ($response->failed()) {
                Log::warning('Busca de endereço falhou', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [];
            }

            $items = $response->json();

            if (! is_array($items)) {
                return [];
            }

            $results = [];

            foreach ($items as $item) {
                if (! is_array($item) || ! isset($item['lat'], $item['lon'])) {
                    continue;
                }

                $result = $this->formatSearchResult($item);

                if ($result !== null) {
                    $results[] = $result;
                }
            }

            return $results;
        } catch (Throwable $exception) {
            Log::warning('Busca de endereço indisponível', [
                'error' => $exception->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, subtitle: string|null, address: string, latitude: float, longitude: float}|null
     */
    private function formatSearchResult(array $item): ?array
    {
        $coordinates = Coordinates::tryFrom($item['lat'] ?? null, $item['lon'] ?? null);

        if ($coordinates === null) {
            return null;
        }

        /** @var array<string, mixed> $address */
        $address = is_array($item['address'] ?? null) ? $item['address'] : [];

        $street = $address['road']
            ?? $address['pedestrian']
            ?? $address['footway']
            ?? $address['path']
            ?? $address['name']
            ?? null;
        $number = $address['house_number'] ?? null;
        $neighborhood = $address['suburb']
            ?? $address['neighbourhood']
            ?? $address['city_district']
            ?? $address['district']
            ?? null;
        $city = $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? null;
        $state = $address['state_code'] ?? $address['state'] ?? null;
        $zip = $address['postcode'] ?? null;

        $formatted = self::formatAddress([
            'street' => $street,
            'number' => $number,
            'neighborhood' => $neighborhood,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
        ]);

        $displayName = is_string($item['display_name'] ?? null)
            ? trim($item['display_name'])
            : '';

        $fullAddress = $formatted !== '' ? $formatted : $displayName;

        if ($fullAddress === '') {
            return null;
        }

        // Aproveita a coordenada da sugestão quando a estimativa geocodificar
        // exatamente este endereço, evitando uma segunda consulta ao Nominatim.
        Cache::put(
            'scheduling:geocode:'.md5(mb_strtolower($fullAddress)),
            [$coordinates->latitude, $coordinates->longitude],
            now()->addDays((int) config('scheduling.geocoding.cache_days', 30)),
        );

        $title = trim(implode(', ', array_filter([$street, $number], fn ($value) => filled($value))));

        $subtitle = implode(' · ', array_filter([
            $neighborhood,
            trim(implode('/', array_filter([$city, $state], fn ($value) => filled($value)))),
            $zip,
        ], fn ($value) => filled($value)));

        return [
            'title' => $title !== '' ? $title : $displayName,
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'address' => $fullAddress,
            'latitude' => $coordinates->latitude,
            'longitude' => $coordinates->longitude,
        ];
    }

    private function request(string $address): ?Coordinates
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('scheduling.geocoding.url'), '/'))
                ->withHeaders(['User-Agent' => (string) config('scheduling.geocoding.user_agent')])
                ->timeout((int) config('scheduling.geocoding.timeout', 8))
                ->acceptJson()
                ->get('/search', array_filter([
                    'q' => $address,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => config('scheduling.geocoding.country_codes'),
                ]));

            if ($response->failed()) {
                Log::warning('Geocoding falhou', ['status' => $response->status(), 'address' => $address]);

                return null;
            }

            $first = $response->json('0');

            return is_array($first) ? Coordinates::tryFrom($first['lat'] ?? null, $first['lon'] ?? null) : null;
        } catch (Throwable $exception) {
            Log::warning('Geocoding indisponível', ['error' => $exception->getMessage(), 'address' => $address]);

            return null;
        }
    }
}
