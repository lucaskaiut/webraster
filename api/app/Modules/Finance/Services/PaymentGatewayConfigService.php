<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\TenantPaymentGatewayConfig;
use App\Modules\Finance\Support\PaymentGatewayResolver;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Validation\ValidationException;

class PaymentGatewayConfigService
{
    public function __construct(
        private readonly PaymentGatewayResolver $gateways,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(?string $gatewayKey = null): array
    {
        $key = $gatewayKey ?: $this->gateways->defaultKey();

        if ($key === null) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['Nenhum gateway de pagamento está ativo neste ambiente.'],
            ]);
        }

        $gateway = $this->gateways->resolve($key);
        $config = TenantPaymentGatewayConfig::query()->where('gateway', $key)->first();

        return [
            'gateway' => $gateway->key(),
            'label' => $gateway->label(),
            'is_active' => (bool) ($config?->is_active ?? false),
            'is_ready' => $gateway->isReady(),
            'webhook_url' => $gateway->webhookUrl(),
            'tenant_uuid' => TenantContext::tenant()?->uuid,
            'credential_schema' => $gateway->credentialSchema(),
            'credentials' => $gateway->publicCredentials(),
            'gateways' => $this->gateways->catalog(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsert(array $data): array
    {
        $key = (string) ($data['gateway'] ?? $this->gateways->defaultKey() ?? '');
        $gateway = $this->gateways->resolve($key);
        $credentials = is_array($data['credentials'] ?? null) ? $data['credentials'] : [];
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

        $gateway->saveConfig($credentials, $isActive);

        return $this->show($gateway->key());
    }
}
