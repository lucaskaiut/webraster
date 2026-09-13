<?php

namespace App\Modules\VehicleData\Gateways;

use App\Modules\VehicleData\Contracts\PlateLookupProvider;
use App\Modules\VehicleData\DTOs\VehicleDataDTO;
use App\Modules\VehicleData\DTOs\VehicleFipeDTO;
use App\Modules\VehicleData\Exceptions\PlateLookupException;
use App\Modules\VehicleData\Models\TenantVehicleDataConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Implementação do provedor "API Placas" (wdapi2.com.br).
 *
 * Endpoint de consulta: GET {base_url}/consulta/{placa}/{token}
 *
 * A URL base é fixa em código (não é configurável). As credenciais (token)
 * são persistidas por tenant em TenantVehicleDataConfig.
 */
final class ApiPlacasProvider implements PlateLookupProvider
{
    private const BASE_URL = 'https://wdapi2.com.br';

    public function key(): string
    {
        return 'apiPlacas';
    }

    public function label(): string
    {
        return 'API Placas';
    }

    public function isReady(): bool
    {
        $config = $this->config();

        return $config !== null
            && $config->is_active
            && filled($config->credential('token'));
    }

    public function credentialSchema(): array
    {
        return [
            [
                'name' => 'token',
                'label' => 'Token',
                'type' => 'password',
                'required' => true,
                'secret' => true,
                'hint' => 'Deixe em branco para manter o token já salvo.',
            ],
        ];
    }

    public function publicCredentials(): array
    {
        $config = $this->config();
        $token = $config?->credential('token');

        return [
            'has_token' => filled($token),
            'token_masked' => $this->maskSecret(is_string($token) ? $token : null),
        ];
    }

    public function saveConfig(array $credentials, bool $isActive): void
    {
        $config = $this->config() ?? new TenantVehicleDataConfig;
        $current = is_array($config->credentials) ? $config->credentials : [];

        $token = array_key_exists('token', $credentials) && filled($credentials['token'])
            ? (string) $credentials['token']
            : ($current['token'] ?? null);

        if (blank($token)) {
            throw ValidationException::withMessages([
                'credentials.token' => ['O token é obrigatório.'],
            ]);
        }

        $config->provider = $this->key();
        $config->is_active = $isActive;
        $config->credentials = ['token' => $token];
        $config->save();
    }

    public function lookup(string $plate): VehicleDataDTO
    {
        if ($this->shouldBypass()) {
            return $this->fakeLookup($plate);
        }

        if (! $this->isReady()) {
            throw PlateLookupException::notConfigured();
        }

        $response = $this->http()->get("/consulta/{$plate}/{$this->token()}");

        if ($response->failed()) {
            throw PlateLookupException::fromHttpStatus($response->status(), $response->json());
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['placa'])) {
            throw PlateLookupException::invalidResponse();
        }

        return $this->map($data);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->timeout(30)
            ->connectTimeout(10)
            ->withHeaders(['User-Agent' => 'WebRaster-VehicleData/1.0']);
    }

    private function token(): string
    {
        return (string) ($this->config()?->credential('token') ?? '');
    }

    private function config(): ?TenantVehicleDataConfig
    {
        return TenantVehicleDataConfig::query()
            ->where('provider', $this->key())
            ->first();
    }

    /**
     * Ambiente local sem token configurado → retorna dados fictícios para testes.
     */
    private function shouldBypass(): bool
    {
        return app()->environment('local') && blank($this->token());
    }

    private function fakeLookup(string $plate): VehicleDataDTO
    {
        // Delay artificial para visualizar o feedback de carregamento no frontend.
        usleep(random_int(200_000, 500_000));

        $brands = ['VW', 'Fiat', 'Chevrolet', 'Toyota', 'Ford'];
        $models = ['Gol', 'Uno', 'Onix', 'Corolla', 'Ka'];
        $colors = ['Branco', 'Prata', 'Preto', 'Vermelho', 'Azul'];
        $transmissions = ['manual', 'automatic', 'automated'];

        $index = 0;
        foreach (str_split($plate) as $char) {
            $index = ($index * 31 + ord($char)) % count($brands);
        }

        $year = 2015 + ($index % 8);

        return new VehicleDataDTO(
            plate: $plate,
            brand: $brands[$index],
            model: $models[$index],
            submodel: $models[$index],
            version: $models[$index],
            year: $year,
            modelYear: $year,
            color: $colors[$index],
            chassis: strtoupper(substr(md5($plate), 0, 17)),
            fuel: 'Flex',
            transmission: $transmissions[$index % 3],
            segment: 'Auto',
            municipality: 'São Paulo',
            uf: 'SP',
            situation: 'Sem restrição',
            fipe: new VehicleFipeDTO(
                code: sprintf('%03d-%d', 1 + $index, $index),
                modelYear: (string) $year,
                fuel: 'Flex',
                referenceMonth: 'setembro de 2026',
                value: 'R$ '.number_format(30000 + $index * 5000, 2, ',', '.'),
                model: $models[$index].' 1.0 Flex',
                brand: $brands[$index],
                score: 100 + $index,
            ),
            extra: null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function map(array $data): VehicleDataDTO
    {
        $extra = is_array($data['extra'] ?? null) ? $data['extra'] : [];
        $fipe = $this->bestFipe($data['fipe']['dados'] ?? null);

        return new VehicleDataDTO(
            plate: (string) ($data['placa'] ?? ''),
            brand: $this->stringOrNull($data['marca'] ?? null),
            model: $this->stringOrNull($data['modelo'] ?? null),
            submodel: $this->stringOrNull($data['SUBMODELO'] ?? null),
            version: $this->stringOrNull($data['VERSAO'] ?? null),
            year: $this->intOrNull($data['ano'] ?? null),
            modelYear: $this->intOrNull($data['anoModelo'] ?? null),
            color: $this->stringOrNull($data['cor'] ?? null),
            chassis: $this->stringOrNull($data['chassi'] ?? null),
            fuel: $this->stringOrNull($extra['combustivel'] ?? null),
            transmission: $this->stringOrNull($extra['caixa_cambio'] ?? null),
            segment: $this->stringOrNull($extra['segmento'] ?? null),
            municipality: $this->stringOrNull($data['municipio'] ?? null),
            uf: $this->stringOrNull($data['uf'] ?? null),
            situation: $this->stringOrNull($data['situacao'] ?? null),
            fipe: $fipe,
            extra: $extra !== [] ? $extra : null,
        );
    }

    /**
     * Seleciona o valor FIPE com maior score (melhor correspondência de nome/marca).
     */
    private function bestFipe(mixed $dados): ?VehicleFipeDTO
    {
        if (! is_array($dados) || $dados === []) {
            return null;
        }

        $best = null;
        $bestScore = -1;

        foreach ($dados as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $score = (int) ($entry['score'] ?? 0);

            if ($best === null || $score > $bestScore) {
                $bestScore = $score;
                $best = $entry;
            }
        }

        if ($best === null) {
            return null;
        }

        return new VehicleFipeDTO(
            code: $this->stringOrNull($best['codigo_fipe'] ?? null),
            modelYear: $this->stringOrNull($best['ano_modelo'] ?? null),
            fuel: $this->stringOrNull($best['combustivel'] ?? null),
            referenceMonth: $this->stringOrNull($best['mes_referencia'] ?? null),
            value: $this->stringOrNull($best['texto_valor'] ?? null),
            model: $this->stringOrNull($best['texto_modelo'] ?? null),
            brand: $this->stringOrNull($best['texto_marca'] ?? null),
            score: $bestScore,
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) $value;
    }

    private function maskSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $len = strlen($value);

        return $len <= 8
            ? str_repeat('*', $len)
            : substr($value, 0, 4).str_repeat('*', max(0, $len - 8)).substr($value, -4);
    }
}
