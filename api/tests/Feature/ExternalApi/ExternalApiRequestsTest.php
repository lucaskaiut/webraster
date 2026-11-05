<?php

namespace Tests\Feature\ExternalApi;

use App\Modules\ExternalApi\Models\ExternalApiRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ExternalApiRequestsTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scheduling.google.routes_url' => 'https://routes.googleapis.com',
            'scheduling.google.places_url' => 'https://places.googleapis.com',
        ]);
    }

    public function test_records_google_calls_with_redacted_credentials(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response(['routes' => [['duration' => '60s']]]),
            'places.googleapis.com/v1/places:autocomplete' => Http::response(['error' => 'denied'], 403),
            'places.googleapis.com/v1/places/*' => Http::response(['formattedAddress' => 'Rua A']),
            'example.com/*' => Http::response('ok'),
        ]);

        Http::withHeaders(['X-Goog-Api-Key' => 'secret-key'])
            ->post('https://routes.googleapis.com/directions/v2:computeRoutes', ['travelMode' => 'DRIVE']);
        Http::post('https://places.googleapis.com/v1/places:autocomplete', ['input' => 'Rua A']);
        Http::get('https://places.googleapis.com/v1/places/abc123', ['key' => 'secret-key', 'languageCode' => 'pt-BR']);
        Http::get('https://example.com/ping');

        $this->assertSame(3, ExternalApiRequest::query()->count());

        $route = ExternalApiRequest::query()->where('service', 'google.routes')->sole();
        $this->assertSame('google', $route->provider);
        $this->assertSame('POST', $route->method);
        $this->assertTrue($route->succeeded);
        $this->assertSame('[redacted]', $route->request_headers['X-Goog-Api-Key']);
        $this->assertStringContainsString('DRIVE', (string) $route->request_body);
        $this->assertStringContainsString('60s', (string) $route->response_body);
        $this->assertStringNotContainsString('secret-key', json_encode($route->toArray()));

        $autocomplete = ExternalApiRequest::query()->where('service', 'google.places.autocomplete')->sole();
        $this->assertFalse($autocomplete->succeeded);
        $this->assertSame(403, $autocomplete->status_code);

        $details = ExternalApiRequest::query()->where('service', 'google.places.details')->sole();
        $this->assertStringContainsString('key=[redacted]', $details->url);
        $this->assertStringNotContainsString('secret-key', $details->url);
    }

    public function test_records_pooled_requests(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['location' => []])]);

        Http::pool(fn ($pool) => [
            $pool->get('https://places.googleapis.com/v1/places/a'),
            $pool->get('https://places.googleapis.com/v1/places/b'),
        ]);

        $this->assertSame(2, ExternalApiRequest::query()->where('service', 'google.places.details')->count());
    }

    public function test_metrics_group_calls_by_service_in_panel_timezone(): void
    {
        $umbrella = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($umbrella));

        // 02:30 UTC de 05/11 = 23:30 de 04/11 em São Paulo.
        $this->logCall('google.routes', '2026-11-05 02:30:00');
        $this->logCall('google.routes', '2026-11-05 12:00:00', succeeded: false);
        $this->logCall('google.places.autocomplete', '2026-11-05 13:00:00');
        $this->logCall('google.routes', '2026-11-10 12:00:00');

        $response = $this->getJson('/api/external-apis/metrics?'.http_build_query([
            'from' => '2026-11-04T00:00:00',
            'to' => '2026-11-05T23:59:59',
            'granularity' => 'day',
            'timezone' => 'America/Sao_Paulo',
        ]))->assertOk();

        $response->assertJsonPath('data.buckets', ['2026-11-04T00:00:00-03:00', '2026-11-05T00:00:00-03:00']);
        $response->assertJsonPath('data.series.0.service', 'google.routes');
        $response->assertJsonPath('data.series.0.data', [1, 1]);
        $response->assertJsonPath('data.series.0.errors', [0, 1]);
        $response->assertJsonPath('data.series.1.service', 'google.places.autocomplete');
        $response->assertJsonPath('data.series.1.data', [0, 1]);
        $response->assertJsonPath('data.summary.total', 3);
        $response->assertJsonPath('data.summary.errors', 1);
    }

    public function test_rejects_too_many_buckets(): void
    {
        $umbrella = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($umbrella));

        $this->getJson('/api/external-apis/metrics?from=2026-01-01&to=2026-11-01&granularity=minute')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('granularity');
    }

    public function test_lists_requests_and_shows_details(): void
    {
        $umbrella = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($umbrella));

        Carbon::setTestNow('2026-11-05 15:00:00');
        $call = $this->logCall('google.routes', '2026-11-05 14:00:00');
        $this->logCall('google.places.autocomplete', '2026-11-05 14:30:00', succeeded: false);

        $this->getJson('/api/external-apis/requests?status=error')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service', 'google.places.autocomplete')
            ->assertJsonMissingPath('data.0.response_body');

        $this->getJson('/api/external-apis/requests/'.$call->id)
            ->assertOk()
            ->assertJsonPath('data.service_label', 'Routes API')
            ->assertJsonPath('data.response_body', '{"ok":true}');
    }

    public function test_is_restricted_to_umbrella_tenant(): void
    {
        [, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $this->getJson('/api/external-apis/metrics')->assertForbidden();
        $this->getJson('/api/external-apis/requests')->assertForbidden();
    }

    public function test_requires_permission(): void
    {
        $umbrella = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createMember($umbrella));

        $this->getJson('/api/external-apis/requests')->assertForbidden();
    }

    private function logCall(string $service, string $at, bool $succeeded = true): ExternalApiRequest
    {
        return ExternalApiRequest::query()->create([
            'provider' => 'google',
            'service' => $service,
            'method' => 'POST',
            'url' => 'https://example.test',
            'status_code' => $succeeded ? 200 : 500,
            'succeeded' => $succeeded,
            'duration_ms' => 120,
            'request_body' => '{}',
            'response_body' => '{"ok":true}',
            'origin' => 'GET /api/scheduling/estimate',
            'created_at' => Carbon::parse($at, 'UTC'),
        ]);
    }
}
