<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Scheduling\Services\Geocoder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocoderTest extends TestCase
{
    private const ADDRESS = 'R. Getúlio Vargas, 1173 - Veneza, Fazenda Rio Grande - PR, 83825-104';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scheduling.geocoding.enabled' => true,
            'scheduling.geocoding.url' => 'http://nominatim.test',
            'scheduling.geocoding.user_agent' => 'NuvemaxTest/1.0',
            'scheduling.geocoding.country_codes' => 'br',
            'scheduling.google.api_key' => null,
            'scheduling.google.places_url' => 'http://google-places.test',
        ]);
    }

    public function test_does_not_cache_failed_requests(): void
    {
        Http::fakeSequence('http://nominatim.test/search*')
            ->push([], 429)
            ->push([['lat' => '-25.6611', 'lon' => '-49.3077']]);

        $geocoder = app(Geocoder::class);

        $this->assertNull($geocoder->geocode(self::ADDRESS));

        $coordinates = $geocoder->geocode(self::ADDRESS);

        $this->assertNotNull($coordinates);
        $this->assertEqualsWithDelta(-25.6611, $coordinates->latitude, 1e-6);
        Http::assertSentCount(2);
    }

    public function test_caches_address_not_found(): void
    {
        Http::fake(['http://nominatim.test/search*' => Http::response([])]);

        $geocoder = app(Geocoder::class);

        $this->assertNull($geocoder->geocode(self::ADDRESS));
        $this->assertNull($geocoder->geocode(self::ADDRESS));

        Http::assertSentCount(1);
    }

    public function test_falls_back_to_google_when_nominatim_does_not_find_the_address(): void
    {
        config(['scheduling.google.api_key' => 'test-key']);

        Http::fake([
            'http://nominatim.test/search*' => Http::response([]),
            'http://google-places.test/v1/places:searchText' => Http::response([
                'places' => [['location' => ['latitude' => -25.6611, 'longitude' => -49.3077]]],
            ]),
        ]);

        $geocoder = app(Geocoder::class);
        $coordinates = $geocoder->geocode(self::ADDRESS);

        $this->assertNotNull($coordinates);
        $this->assertEqualsWithDelta(-25.6611, $coordinates->latitude, 1e-6);
        $this->assertEqualsWithDelta(-49.3077, $coordinates->longitude, 1e-6);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'places:searchText')
            && $request->hasHeader('X-Goog-Api-Key', 'test-key')
            && $request->hasHeader('X-Goog-FieldMask', 'places.location')
            && $request['textQuery'] === self::ADDRESS
            && $request['regionCode'] === 'br');

        // O resultado do Google também vai para o cache.
        $geocoder->geocode(self::ADDRESS);
        Http::assertSentCount(2);
    }

    public function test_does_not_cache_not_found_when_a_provider_failed(): void
    {
        config(['scheduling.google.api_key' => 'test-key']);

        Http::fake([
            'http://nominatim.test/search*' => Http::response([], 429),
            'http://google-places.test/v1/places:searchText' => Http::response([]),
        ]);

        $geocoder = app(Geocoder::class);

        $this->assertNull($geocoder->geocode(self::ADDRESS));
        $this->assertNull($geocoder->geocode(self::ADDRESS));

        Http::assertSentCount(4);
    }
}
