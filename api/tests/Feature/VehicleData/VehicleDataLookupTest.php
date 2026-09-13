<?php

namespace Tests\Feature\VehicleData;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\VehicleData\Models\TenantVehicleDataConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class VehicleDataLookupTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_lookup_returns_normalized_vehicle_data(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->createVehicleDataConfig($tenant, 'test-token');

        Http::fake([
            'wdapi2.com.br/*' => Http::response([
                'MARCA' => 'VW',
                'MODELO' => 'CROSSFOX',
                'SUBMODELO' => 'CROSSFOX',
                'VERSAO' => 'CROSSFOX',
                'ano' => '2007',
                'anoModelo' => '2007',
                'chassi' => '*****10137',
                'cor' => 'Prata',
                'marca' => 'VW',
                'modelo' => 'CROSSFOX',
                'municipio' => 'São Leopoldo',
                'placa' => 'INT8C36',
                'situacao' => 'Sem restrição',
                'uf' => 'RS',
                'extra' => [
                    'combustivel' => 'Alcool / Gasolina',
                    'caixa_cambio' => '',
                    'segmento' => 'Auto',
                ],
                'fipe' => [
                    'dados' => [
                        [
                            'score' => 80,
                            'codigo_fipe' => '005225-6',
                            'ano_modelo' => '2007',
                            'combustivel' => 'Gasolina',
                            'mes_referencia' => 'maio de 2022 ',
                            'texto_valor' => 'R$ 27.000,00',
                            'texto_modelo' => 'CROSSFOX 1.6 8V',
                            'texto_marca' => 'VW - VolksWagen',
                        ],
                        [
                            'score' => 101,
                            'codigo_fipe' => '005225-7',
                            'ano_modelo' => '2007',
                            'combustivel' => 'Gasolina',
                            'mes_referencia' => 'maio de 2022 ',
                            'texto_valor' => 'R$ 28.799,00',
                            'texto_modelo' => 'CROSSFOX 1.6 Mi Total Flex 8V 5p',
                            'texto_marca' => 'VW - VolksWagen',
                        ],
                    ],
                ],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/vehicle-data/lookup?plate=INT8C36')
            ->assertOk()
            ->assertJsonPath('data.plate', 'INT8C36')
            ->assertJsonPath('data.brand', 'VW')
            ->assertJsonPath('data.model', 'CROSSFOX')
            ->assertJsonPath('data.year', 2007)
            ->assertJsonPath('data.model_year', 2007)
            ->assertJsonPath('data.color', 'Prata')
            ->assertJsonPath('data.fuel', 'Alcool / Gasolina')
            ->assertJsonPath('data.fipe.code', '005225-7')
            ->assertJsonPath('data.fipe.score', 101)
            ->assertJsonPath('data.fipe.value', 'R$ 28.799,00')
            ->assertJsonPath('data.fipe.model', 'CROSSFOX 1.6 Mi Total Flex 8V 5p');
    }

    public function test_lookup_normalizes_plate_and_rejects_invalid(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->createVehicleDataConfig($tenant, 'test-token');

        Sanctum::actingAs($this->createAdmin($tenant));

        Http::fake([
            'wdapi2.com.br/*' => Http::response([
                'placa' => 'ABC1234',
                'marca' => 'Fiat',
            ]),
        ]);

        $this->getJson('/api/vehicle-data/lookup?plate=abc-1234')
            ->assertOk()
            ->assertJsonPath('data.plate', 'ABC1234')
            ->assertJsonPath('data.brand', 'Fiat');

        $this->getJson('/api/vehicle-data/lookup?plate=123')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');
    }

    public function test_lookup_maps_provider_no_results_error(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->createVehicleDataConfig($tenant, 'test-token');

        Http::fake([
            'wdapi2.com.br/*' => Http::response(['message' => 'Sem resultados!'], 406),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/vehicle-data/lookup?plate=ABC1234')
            ->assertStatus(406)
            ->assertJsonPath('message', 'Sem resultados!');
    }

    public function test_lookup_returns_unavailable_when_provider_not_configured(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/vehicle-data/lookup?plate=ABC1234')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Consulta de placa indisponível: provedor não configurado.');
    }

    public function test_config_show_and_update_persist_per_tenant(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/vehicle-data/config')
            ->assertOk()
            ->assertJsonPath('data.provider', 'apiPlacas')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_ready', false);

        $this->putJson('/api/vehicle-data/config', [
            'provider' => 'apiPlacas',
            'is_active' => true,
            'credentials' => ['token' => 'secret-token'],
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_ready', true)
            ->assertJsonPath('data.credentials.has_token', true);

        $this->assertDatabaseHas('tenant_vehicle_data_configs', [
            'tenant_id' => $tenant->getKey(),
            'provider' => 'apiPlacas',
            'is_active' => true,
        ]);
    }

    public function test_lookup_bypasses_with_fake_data_on_local_without_token(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->app->detectEnvironment(fn () => 'local');

        try {
            $this->getJson('/api/vehicle-data/lookup?plate=ABC1234')
                ->assertOk()
                ->assertJsonPath('data.plate', 'ABC1234')
                ->assertJsonPath('data.brand', 'VW')
                ->assertJsonPath('data.model', 'Gol')
                ->assertJsonPath('data.color', 'Branco')
                ->assertJsonPath('data.year', 2015)
                ->assertJsonPath('data.transmission', 'manual')
                ->assertJsonPath('data.fipe.code', '001-0')
                ->assertJsonPath('data.fipe.score', 100)
                ->assertJsonPath('data.fipe.value', 'R$ 30.000,00');
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    private function createVehicleDataConfig(Tenant $tenant, string $token): TenantVehicleDataConfig
    {
        $config = new TenantVehicleDataConfig;
        $config->forceFill([
            'tenant_id' => $tenant->getKey(),
            'provider' => 'apiPlacas',
            'is_active' => true,
            'credentials' => ['token' => $token],
        ]);
        $config->save();

        return $config;
    }
}
