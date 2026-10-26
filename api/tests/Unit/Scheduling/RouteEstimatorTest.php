<?php

namespace Tests\Unit\Scheduling;

use App\Modules\Scheduling\Services\RouteEstimator;
use App\Modules\Scheduling\Support\Coordinates;
use App\Modules\Scheduling\Support\TravelEstimate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteEstimatorTest extends TestCase
{
    private const FROM_LAT = -25.4284;

    private const FROM_LNG = -49.2733;

    private const TO_LAT = -25.4000;

    private const TO_LNG = -49.3000;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'scheduling.routing.enabled' => true,
            'scheduling.routing.provider' => 'google',
            'scheduling.routing.url' => 'http://osrm.test',
            'scheduling.routing.timeout' => 2,
            'scheduling.google.api_key' => 'google-test-key',
            'scheduling.google.routes_url' => 'http://google-routes.test',
            'scheduling.google.language' => 'pt-BR',
            'scheduling.google.timeout' => 2,
        ]);
    }

    #[Test]
    public function google_route_ignores_traffic_conditions(): void
    {
        Http::fake([
            'http://google-routes.test/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'duration' => '1800s',
                    'distanceMeters' => 15000,
                    'polyline' => ['encodedPolyline' => 'ipkcFfichVnP@j@BLoFVwM{E?'],
                ]],
            ]),
        ]);

        $estimate = app(RouteEstimator::class)->estimate(
            new Coordinates(self::FROM_LAT, self::FROM_LNG),
            new Coordinates(self::TO_LAT, self::TO_LNG),
        );

        $this->assertSame(TravelEstimate::SOURCE_GOOGLE, $estimate->source);
        $this->assertSame(30, $estimate->minutes);
        $this->assertSame(15000, $estimate->distanceMeters);
        $this->assertNotEmpty($estimate->geometry);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), ':computeRoutes')) {
                return false;
            }

            return $request->hasHeader('X-Goog-Api-Key', 'google-test-key')
                && $request['routingPreference'] === 'TRAFFIC_UNAWARE'
                && ! isset($request['departureTime'])
                && ! isset($request['trafficModel']);
        });
    }

    #[Test]
    public function falls_back_to_osrm_when_google_is_unavailable(): void
    {
        Http::fake([
            'http://google-routes.test/directions/v2:computeRoutes' => Http::response(
                ['error' => ['message' => 'unavailable']],
                503,
            ),
            'http://osrm.test/route/v1/driving/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'duration' => 900,
                    'distance' => 7000,
                    'geometry' => ['coordinates' => [
                        [self::FROM_LNG, self::FROM_LAT],
                        [self::TO_LNG, self::TO_LAT],
                    ]],
                ]],
            ]),
        ]);

        $estimate = app(RouteEstimator::class)->estimate(
            new Coordinates(self::FROM_LAT, self::FROM_LNG),
            new Coordinates(self::TO_LAT, self::TO_LNG),
        );

        $this->assertSame(TravelEstimate::SOURCE_OSRM, $estimate->source);
        $this->assertSame(15, $estimate->minutes);
        $this->assertSame(7000, $estimate->distanceMeters);
    }
}
