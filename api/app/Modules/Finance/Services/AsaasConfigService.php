<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\AsaasEnvironment;
use App\Modules\Finance\Models\TenantAsaasConfig;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AsaasConfigService
{
    public function get(): ?TenantAsaasConfig
    {
        return TenantAsaasConfig::query()->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(array $data): TenantAsaasConfig
    {
        $config = $this->get() ?? new TenantAsaasConfig;

        if (isset($data['environment'])) {
            $config->environment = $data['environment'] instanceof AsaasEnvironment
                ? $data['environment']
                : AsaasEnvironment::from((string) $data['environment']);
        } elseif (! $config->exists) {
            $config->environment = AsaasEnvironment::SANDBOX;
        }

        if (array_key_exists('api_key', $data) && filled($data['api_key'])) {
            $config->api_key = (string) $data['api_key'];
        } elseif (! $config->exists) {
            throw ValidationException::withMessages([
                'api_key' => ['A chave de API é obrigatória.'],
            ]);
        }

        if (array_key_exists('webhook_token', $data) && filled($data['webhook_token'])) {
            $config->webhook_token = (string) $data['webhook_token'];
        } elseif (blank($config->webhook_token)) {
            $config->webhook_token = Str::random(48);
        }

        if (array_key_exists('is_active', $data)) {
            $config->is_active = (bool) $data['is_active'];
        } elseif (! $config->exists) {
            $config->is_active = true;
        }

        $config->save();

        return $config->refresh();
    }
}
