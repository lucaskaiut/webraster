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
