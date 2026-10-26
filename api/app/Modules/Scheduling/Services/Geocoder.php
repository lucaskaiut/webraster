<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Support\Coordinates;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Converte endereços em coordenadas e monta sugestões de autocomplete.
 *
 * O autocomplete prioriza a Places API (Google) quando há chave configurada,
 * mantendo o Nominatim (OpenStreetMap) como alternativa.
 */
class Geocoder
{
    /**
     * O autocomplete só é disparado a partir de duas palavras digitadas.
     */
    public static function hasMinimumWords(string $query): bool
    {
        $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) && count($words) >= 2;
    }

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
     * Sugestões de endereço para autocomplete.
     *
     * @return list<array{title: string, subtitle: string|null, address: string, latitude: float|null, longitude: float|null}>
     */
    public function search(string $query, int $limit = 6): array
    {
        $query = trim($query);

        if (! self::hasMinimumWords($query) || ! config('scheduling.geocoding.enabled')) {
            return [];
        }

        $limit = max(1, min($limit, 8));
        $google = $this->googleApiKey() !== null;
        $provider = $google ? 'google' : 'nominatim';
        $key = 'scheduling:geocode:search:'.$provider.':'.md5(mb_strtolower($query)).':'.$limit;

        /** @var list<array<string, mixed>>|null $cached */
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $results = $google ? $this->requestGoogleSearch($query, $limit) : $this->requestSearch($query, $limit);

        if ($google && $results === []) {
            // Se o Google estiver indisponível, ainda é possível sugerir
            // endereços pelo Nominatim em vez de deixar o campo sem ajuda.
            $results = $this->requestSearch($query, $limit);
        }

        Cache::put(
            $key,
            $results,
            $results === [] ? now()->addMinute() : now()->addMinutes(10),
        );

        return $results;
    }

    /**
     * @return list<array{title: string, subtitle: string|null, address: string, latitude: float|null, longitude: float|null}>
     */
    private function requestGoogleSearch(string $query, int $limit): array
    {
        $key = $this->googleApiKey();

        if ($key === null) {
            return [];
        }

        try {
            $regions = $this->googleRegions();

            $response = Http::baseUrl(rtrim((string) config('scheduling.google.places_url'), '/'))
                ->withHeaders([
                    'X-Goog-Api-Key' => $key,
                    'X-Goog-FieldMask' => implode(',', [
                        'suggestions.placePrediction.placeId',
                        'suggestions.placePrediction.text.text',
                        'suggestions.placePrediction.structuredFormat.mainText.text',
                        'suggestions.placePrediction.structuredFormat.secondaryText.text',
                    ]),
                ])
                ->timeout((int) config('scheduling.google.timeout', 8))
                ->acceptJson()
                ->post('/v1/places:autocomplete', array_filter([
                    'input' => $query,
                    'languageCode' => (string) config('scheduling.google.language', 'pt-BR'),
                    'regionCode' => $regions[0] ?? null,
                    'includedRegionCodes' => $regions === [] ? null : $regions,
                ]));

            if ($response->failed()) {
                Log::warning('Busca de endereço no Google falhou', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [];
            }

            $suggestions = $response->json('suggestions');

            if (! is_array($suggestions)) {
                return [];
            }

            $predictions = [];

            foreach ($suggestions as $suggestion) {
                $prediction = is_array($suggestion) ? ($suggestion['placePrediction'] ?? null) : null;

                if (! is_array($prediction)) {
                    continue;
                }

                $placeId = trim((string) ($prediction['placeId'] ?? ''));

                if ($placeId === '') {
                    continue;
                }

                $predictions[$placeId] = $prediction;

                if (count($predictions) >= $limit) {
                    break;
                }
            }

            if ($predictions === []) {
                return [];
            }

            $details = $this->googlePlaceDetails(array_keys($predictions));
            $results = [];

            foreach ($predictions as $placeId => $prediction) {
                $detail = $details[$placeId] ?? null;
                $coordinates = is_array($detail)
                    ? Coordinates::tryFrom(
                        $detail['location']['latitude'] ?? null,
                        $detail['location']['longitude'] ?? null,
                    )
                    : null;
                $address = trim((string) (
                    $detail['formattedAddress']
                    ?? $prediction['text']['text']
                    ?? ''
                ));

                if ($address === '') {
                    continue;
                }

                $title = trim((string) ($prediction['structuredFormat']['mainText']['text'] ?? ''));
                $subtitle = trim((string) ($prediction['structuredFormat']['secondaryText']['text'] ?? ''));

                if ($coordinates !== null) {
                    // Evita uma nova consulta ao geocodificar exatamente este endereço.
                    Cache::put(
                        'scheduling:geocode:'.md5(mb_strtolower($address)),
                        [$coordinates->latitude, $coordinates->longitude],
                        now()->addDays((int) config('scheduling.geocoding.cache_days', 30)),
                    );
                }

                $results[] = [
                    'title' => $title !== '' ? $title : $address,
                    'subtitle' => $subtitle !== '' ? $subtitle : null,
                    'address' => $address,
                    'latitude' => $coordinates?->latitude,
                    'longitude' => $coordinates?->longitude,
                ];
            }

            return $results;
        } catch (Throwable $exception) {
            Log::warning('Busca de endereço no Google indisponível', [
                'error' => $exception->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * Consulta os detalhes dos locais em paralelo para obter endereço e coordenadas.
     *
     * @param  list<string>  $placeIds
     * @return array<string, array<string, mixed>>
     */
    private function googlePlaceDetails(array $placeIds): array
    {
        $key = $this->googleApiKey();

        if ($key === null || $placeIds === []) {
            return [];
        }

        try {
            $language = (string) config('scheduling.google.language', 'pt-BR');
            $region = $this->googleRegions()[0] ?? null;

            $responses = Http::pool(function (Pool $pool) use ($key, $language, $placeIds, $region) {
                $requests = [];

                foreach ($placeIds as $index => $placeId) {
                    $requests[$index] = $pool
                        ->baseUrl(rtrim((string) config('scheduling.google.places_url'), '/'))
                        ->withHeaders([
                            'X-Goog-Api-Key' => $key,
                            'X-Goog-FieldMask' => 'formattedAddress,location',
                        ])
                        ->timeout((int) config('scheduling.google.timeout', 8))
                        ->acceptJson()
                        ->get('/v1/places/'.rawurlencode($placeId), array_filter([
                            'languageCode' => $language,
                            'regionCode' => $region,
                        ]));
                }

                return $requests;
            });
        } catch (Throwable $exception) {
            Log::warning('Detalhes de endereço no Google indisponíveis', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        $details = [];

        foreach ($responses as $index => $response) {
            if (! $response instanceof Response || $response->failed()) {
                continue;
            }

            $data = $response->json();

            if (is_array($data)) {
                $details[$placeIds[$index]] = $data;
            }
        }

        return $details;
    }

    private function googleApiKey(): ?string
    {
        $key = trim((string) config('scheduling.google.api_key'));

        return $key === '' ? null : $key;
    }

    /**
     * @return list<string>
     */
    private function googleRegions(): array
    {
        return array_values(array_filter(array_map(
            fn (string $code) => mb_strtolower(trim($code)),
            explode(',', (string) config('scheduling.geocoding.country_codes', 'br')),
        )));
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
