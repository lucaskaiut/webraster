<?php

namespace Tests\Feature\Scheduling;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AddressSearchTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scheduling.geocoding.enabled' => true,
            'scheduling.geocoding.url' => 'http://nominatim.test',
            'scheduling.geocoding.user_agent' => 'NuvemaxTest/1.0',
            'scheduling.geocoding.country_codes' => 'br',
            'scheduling.geocoding.timeout' => 2,
        ]);
    }

    public function test_returns_address_suggestions_formatted_for_the_form(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        Http::fake([
            'http://nominatim.test/search*' => Http::response([
                [
                    'lat' => '-23.5558',
                    'lon' => '-46.6614',
                    'display_name' => 'Rua Augusta, 1500, Consolação, São Paulo, SP, 01304-001, Brasil',
                    'address' => [
                        'road' => 'Rua Augusta',
                        'house_number' => '1500',
                        'suburb' => 'Consolação',
                        'city' => 'São Paulo',
                        'state_code' => 'SP',
                        'postcode' => '01304-001',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/scheduling/address-search?q=Rua%20Augusta&limit=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Rua Augusta, 1500')
            ->assertJsonPath('data.0.subtitle', 'Consolação · São Paulo/SP · 01304-001')
            ->assertJsonPath('data.0.address', 'Rua Augusta, 1500 - Consolação - São Paulo/SP - 01304-001')
            ->assertJsonPath('data.0.latitude', -23.5558)
            ->assertJsonPath('data.0.longitude', -46.6614);

        Http::assertSent(function ($request): bool {
            if (! str_starts_with($request->url(), 'http://nominatim.test/search')) {
                return false;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['q'] === 'Rua Augusta'
                && $query['format'] === 'jsonv2'
                && $query['addressdetails'] === '1'
                && $query['limit'] === '5'
                && $query['countrycodes'] === 'br';
        });
    }

    public function test_caches_repeated_searches(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        Http::fake([
            'http://nominatim.test/search*' => Http::response([
                [
                    'lat' => '-25.4284',
                    'lon' => '-49.2733',
                    'display_name' => 'Rua XV de Novembro, 100, Centro, Curitiba, PR, 80020-310, Brasil',
                    'address' => [
                        'road' => 'Rua XV de Novembro',
                        'house_number' => '100',
                        'suburb' => 'Centro',
                        'city' => 'Curitiba',
                        'state_code' => 'PR',
                        'postcode' => '80020-310',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/scheduling/address-search?q=Rua%20XV%20de%20Novembro')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/scheduling/address-search?q=Rua%20XV%20de%20Novembro')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Http::assertSentCount(1);
    }

    public function test_requires_at_least_three_characters(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        Http::fake();

        $this->getJson('/api/scheduling/address-search?q=ab')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');

        Http::assertNothingSent();
    }

    public function test_returns_empty_when_geocoding_is_disabled(): void
    {
        config(['scheduling.geocoding.enabled' => false]);

        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        Http::fake();

        $this->getJson('/api/scheduling/address-search?q=Rua%20Augusta')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Http::assertNothingSent();
    }
}
