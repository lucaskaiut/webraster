<?php

namespace App\Modules\VehicleData\Services;

use App\Modules\VehicleData\Models\TenantVehicleDataConfig;
use App\Modules\VehicleData\Support\PlateLookupResolver;
use Illuminate\Validation\ValidationException;

class VehicleDataConfigService
{
    public function __construct(
        private readonly PlateLookupResolver $providers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(?string $providerKey = null): array
    {
        $key = $providerKey ?: $this->providers->defaultKey();

        if ($key === '') {
            throw ValidationException::withMessages([
                'provider' => ['Nenhum provedor de consulta de placa está disponível.'],
            ]);
        }

        $provider = $this->providers->resolve($key);
        $config = TenantVehicleDataConfig::query()->where('provider', $key)->first();

        return [
            'provider' => $provider->key(),
            'label' => $provider->label(),
            'is_active' => (bool) ($config?->is_active ?? false),
            'is_ready' => $provider->isReady(),
            'credential_schema' => $provider->credentialSchema(),
            'credentials' => $provider->publicCredentials(),
            'providers' => $this->providers->catalog(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsert(array $data): array
    {
        $key = (string) ($data['provider'] ?? $this->providers->defaultKey());
        $provider = $this->providers->resolve($key);
        $credentials = is_array($data['credentials'] ?? null) ? $data['credentials'] : [];
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : false;

        $provider->saveConfig($credentials, $isActive);

        return $this->show($provider->key());
    }
}
