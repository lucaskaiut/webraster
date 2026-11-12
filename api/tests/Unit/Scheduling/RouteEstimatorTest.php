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

    #[Test]
    public function nearby_coordinates_reuse_the_cached_google_route(): void
    {
        Http::fake([
            'http://google-routes.test/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'duration' => '600s',
                    'distanceMeters' => 5000,
                ]],
            ]),
        ]);

        (new RouteEstimator)->estimate(
            new Coordinates(self::FROM_LAT, self::FROM_LNG),
            new Coordinates(self::TO_LAT, self::TO_LNG),
        );
        // Poucos metros de diferença: arredonda para a mesma chave.
        (new RouteEstimator)->estimate(
            new Coordinates(self::FROM_LAT + 0.00002, self::FROM_LNG - 0.00002),
            new Coordinates(self::TO_LAT - 0.00002, self::TO_LNG + 0.00002),
        );

        Http::assertSentCount(1);
    }

    #[Test]
    public function google_route_stays_cached_for_weeks(): void
    {
        Http::fake([
            'http://google-routes.test/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'duration' => '600s',
                    'distanceMeters' => 5000,
                ]],
            ]),
        ]);

        $from = new Coordinates(self::FROM_LAT, self::FROM_LNG);
        $to = new Coordinates(self::TO_LAT, self::TO_LNG);

        (new RouteEstimator)->estimate($from, $to);
        $this->travel(20)->days();
        (new RouteEstimator)->estimate($from, $to);

        Http::assertSentCount(1);
    }

    #[Test]
    public function osrm_fallback_is_cached_only_briefly(): void
    {
        Http::fake([
            'http://google-routes.test/directions/v2:computeRoutes' => Http::sequence()
                ->push(['error' => ['message' => 'unavailable']], 503)
                ->push([
                    'routes' => [[
                        'duration' => '600s',
                        'distanceMeters' => 5000,
                    ]],
                ]),
            'http://osrm.test/route/v1/driving/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'duration' => 900,
                    'distance' => 7000,
                ]],
            ]),
        ]);

        $from = new Coordinates(self::FROM_LAT, self::FROM_LNG);
        $to = new Coordinates(self::TO_LAT, self::TO_LNG);

        $this->assertSame(TravelEstimate::SOURCE_OSRM, (new RouteEstimator)->estimate($from, $to)->source);
        $this->assertSame(TravelEstimate::SOURCE_OSRM, (new RouteEstimator)->estimate($from, $to)->source);

        $this->travel(2)->hours();

        $this->assertSame(TravelEstimate::SOURCE_GOOGLE, (new RouteEstimator)->estimate($from, $to)->source);
    }

    #[Test]
    public function repeated_pairs_in_the_same_request_hit_the_provider_once_without_cache(): void
    {
        // Cache persistente indisponível (ex.: logo após uma limpeza).
        config(['cache.default' => 'null']);
        Cache::forgetDriver();

        Http::fake([
            'http://google-routes.test/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'duration' => '600s',
                    'distanceMeters' => 5000,
                ]],
            ]),
        ]);

        $from = new Coordinates(self::FROM_LAT, self::FROM_LNG);
        $to = new Coordinates(self::TO_LAT, self::TO_LNG);

        // Serviços diferentes da mesma requisição recebem a mesma instância.
        $this->assertSame(app(RouteEstimator::class), app(RouteEstimator::class));

        for ($i = 0; $i < 200; $i++) {
            app(RouteEstimator::class)->estimate($from, $to);
            app(RouteEstimator::class)->estimate($to, $from);
        }

        Http::assertSentCount(2);
    }

    private function useValhalla(string $fallback = 'google'): void
    {
        config([
            'scheduling.routing.provider' => 'valhalla',
            'scheduling.valhalla.url' => 'http://rotas.test',
            'scheduling.valhalla.token' => 'api_token-de-teste',
            'scheduling.valhalla.timeout' => 2,
            'scheduling.valhalla.fallback' => $fallback,
        ]);
    }

    #[Test]
    public function valhalla_route_uses_the_authenticated_proxy(): void
    {
        $this->useValhalla();

        Http::fake([
            'http://rotas.test/v1/route' => Http::response([
                'trip' => [
                    'summary' => ['time' => 1261.4, 'length' => 10.194],
                    // polyline6 de (-25.4284, -49.2733) → (-25.4, -49.3)
                    'legs' => [['shape' => '~y_oo@f|k~|A_nv@vcs@']],
                ],
            ]),
        ]);

        $estimate = app(RouteEstimator::class)->estimate(
            new Coordinates(self::FROM_LAT, self::FROM_LNG),
            new Coordinates(self::TO_LAT, self::TO_LNG),
        );

        $this->assertSame(TravelEstimate::SOURCE_VALHALLA, $estimate->source);
        $this->assertSame(25, $estimate->minutes);
        $this->assertSame(10194, $estimate->distanceMeters);
        $this->assertEqualsWithDelta(self::FROM_LAT, $estimate->geometry[0][0], 0.0001);
        $this->assertEqualsWithDelta(self::TO_LNG, $estimate->geometry[1][1], 0.0001);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://rotas.test/v1/route'
            && $request->hasHeader('Authorization', 'Bearer api_token-de-teste')
            && $request['costing'] === 'auto'
            && $request['locations'][0]['lat'] === self::FROM_LAT
            && $request['locations'][1]['lon'] === self::TO_LNG);
        Http::assertSentCount(1);
    }

    #[Test]
    public function valhalla_failure_falls_back_to_google_and_is_cached_briefly(): void
    {
        $this->useValhalla();

        Http::fake([
            'http://rotas.test/v1/route' => Http::sequence()
                ->push(['error' => 'Servidor de rotas indisponível.'], 502)
                ->push(['trip' => ['summary' => ['time' => 300, 'length' => 4]]]),
            'http://google-routes.test/directions/v2:computeRoutes' => Http::response([
                'routes' => [['duration' => '600s', 'distanceMeters' => 5000]],
            ]),
        ]);

        $estimate = app(RouteEstimator::class)->estimate(
            new Coordinates(self::FROM_LAT, self::FROM_LNG),
            new Coordinates(self::TO_LAT, self::TO_LNG),
        );

        $this->assertSame(TravelEstimate::SOURCE_GOOGLE, $estimate->source);
        $this->assertSame(10, $estimate->minutes);

        // A reserva fica pouco tempo em cache: depois disso o Valhalla é tentado de novo.
        $this->travel((int) config('scheduling.routing.fallback_cache_minutes') + 1)->minutes();

        $retry = (new RouteEstimator)->estimate(
            new Coordinates(self::FROM_LAT, self::FROM_LNG),
            new Coordinates(self::TO_LAT, self::TO_LNG),
        );

        $this->assertSame(TravelEstimate::SOURCE_VALHALLA, $retry->source);
        $this->assertSame(5, $retry->minutes);
    }

    #[Test]
    public function valhalla_can_fall_back_straight_to_osrm(): void
    {
        $this->useValhalla('osrm');

        Http::fake([
            'http://rotas.test/v1/route' => Http::response(['error' => 'down'], 503),
            'http://osrm.test/route/v1/driving/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['duration' => 900, 'distance' => 7000, 'geometry' => ['coordinates' => []]]],
            ]),
        ]);

        $estimate = app(RouteEstimator::class)->estimate(
            new Coordinates(self::FROM_LAT, self::FROM_LNG),
            new Coordinates(self::TO_LAT, self::TO_LNG),
        );

        $this->assertSame(TravelEstimate::SOURCE_OSRM, $estimate->source);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'google-routes.test'));
    }

    #[Test]
    public function valhalla_without_token_uses_google(): void
    {
        $this->useValhalla();
        config(['scheduling.valhalla.token' => '']);

        Http::fake([
            'http://google-routes.test/directions/v2:computeRoutes' => Http::response([
                'routes' => [['duration' => '600s', 'distanceMeters' => 5000]],
            ]),
        ]);

        $estimate = app(RouteEstimator::class)->estimate(
            new Coordinates(self::FROM_LAT, self::FROM_LNG),
            new Coordinates(self::TO_LAT, self::TO_LNG),
        );

        $this->assertSame(TravelEstimate::SOURCE_GOOGLE, $estimate->source);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'rotas.test'));
    }
}
