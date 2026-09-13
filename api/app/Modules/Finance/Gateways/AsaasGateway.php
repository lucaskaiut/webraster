<?php

namespace App\Modules\Finance\Gateways;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Contracts\PaymentGatewayInterface;
use App\Modules\Finance\DTOs\CreateGatewayPaymentDTO;
use App\Modules\Finance\DTOs\GatewayCustomerDTO;
use App\Modules\Finance\DTOs\GatewayPaymentDTO;
use App\Modules\Finance\DTOs\GatewayWebhookEventDTO;
use App\Modules\Finance\Enums\GatewayWebhookEventType;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Gateways\Asaas\AsaasClient;
use App\Modules\Finance\Gateways\Asaas\AsaasException;
use App\Modules\Finance\Gateways\Asaas\AsaasPaymentMapper;
use App\Modules\Finance\Models\FinanceGatewayCustomer;
use App\Modules\Finance\Models\TenantPaymentGatewayConfig;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AsaasGateway implements PaymentGatewayInterface
{
    private const ENVIRONMENTS = [
        'sandbox' => 'https://api-sandbox.asaas.com/v3',
        'production' => 'https://api.asaas.com/v3',
    ];

    public function key(): string
    {
        return 'asaas';
    }

    public function label(): string
    {
        return 'Asaas';
    }

    public function supports(PaymentMethod $method): bool
    {
        return in_array($method, [PaymentMethod::PIX, PaymentMethod::BOLETO, PaymentMethod::CREDIT_CARD], true);
    }

    public function isReady(): bool
    {
        $config = $this->config();

        return $config !== null
            && $config->is_active
            && filled($config->credential('api_key'));
    }

    public function credentialSchema(): array
    {
        return [
            [
                'name' => 'environment',
                'label' => 'Ambiente',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'sandbox', 'label' => 'Sandbox'],
                    ['value' => 'production', 'label' => 'Produção'],
                ],
            ],
            [
                'name' => 'api_key',
                'label' => 'API key',
                'type' => 'password',
                'required' => true,
                'secret' => true,
                'hint' => 'Deixe em branco para manter a chave já salva.',
            ],
            [
                'name' => 'webhook_token',
                'label' => 'Token do webhook',
                'type' => 'password',
                'required' => false,
                'secret' => true,
                'hint' => 'Mínimo 32 caracteres. Use o mesmo token no painel do Asaas (asaas-access-token). Em branco na primeira configuração, o sistema gera um automaticamente.',
            ],
        ];
    }

    public function publicCredentials(): array
    {
        $config = $this->config();
        $apiKey = $config?->credential('api_key');
        $token = $config?->credential('webhook_token');

        return [
            'environment' => $config?->credential('environment', 'sandbox'),
            'has_api_key' => filled($apiKey),
            'api_key_masked' => $this->maskSecret(is_string($apiKey) ? $apiKey : null),
            'has_webhook_token' => filled($token),
        ];
    }

    public function saveConfig(array $credentials, bool $isActive): void
    {
        $config = $this->config() ?? new TenantPaymentGatewayConfig;
        $current = is_array($config->credentials) ? $config->credentials : [];

        $environment = (string) ($credentials['environment'] ?? $current['environment'] ?? 'sandbox');
        if (! array_key_exists($environment, self::ENVIRONMENTS)) {
            throw ValidationException::withMessages([
                'credentials.environment' => ['Ambiente inválido.'],
            ]);
        }

        $apiKey = array_key_exists('api_key', $credentials) && filled($credentials['api_key'])
            ? (string) $credentials['api_key']
            : ($current['api_key'] ?? null);

        if (blank($apiKey)) {
            throw ValidationException::withMessages([
                'credentials.api_key' => ['A chave de API é obrigatória.'],
            ]);
        }

        $webhookToken = array_key_exists('webhook_token', $credentials) && filled($credentials['webhook_token'])
            ? (string) $credentials['webhook_token']
            : ($current['webhook_token'] ?? null);

        if (filled($webhookToken) && strlen((string) $webhookToken) < 32) {
            throw ValidationException::withMessages([
                'credentials.webhook_token' => ['O token do webhook deve ter no mínimo 32 caracteres.'],
            ]);
        }

        if (blank($webhookToken)) {
            $webhookToken = Str::random(48);
        }

        $config->gateway = $this->key();
        $config->is_active = $isActive;
        $config->credentials = [
            'environment' => $environment,
            'api_key' => $apiKey,
            'webhook_token' => $webhookToken,
        ];
        $config->save();
    }

    public function webhookUrl(): string
    {
        $tenant = TenantContext::tenant();
        $uuid = $tenant?->uuid ?? '{tenantUuid}';

        return rtrim((string) config('app.url'), '/').'/api/webhooks/payments/'.$this->key().'/'.$uuid;
    }

    public function ensureCustomer(Client $client): GatewayCustomerDTO
    {
        $email = $client->financial_email ?: $client->email;
        $document = preg_replace('/\D+/', '', (string) $client->document) ?: '';

        if (blank($email)) {
            throw ValidationException::withMessages([
                'client_id' => ['O cliente precisa de e-mail cadastrado para gerar o pagamento.'],
            ]);
        }

        if ($document === '') {
            throw ValidationException::withMessages([
                'client_id' => ['O cliente precisa de CPF/CNPJ cadastrado para gerar o pagamento.'],
            ]);
        }

        $cached = FinanceGatewayCustomer::query()
            ->where('gateway', $this->key())
            ->where('client_id', $client->getKey())
            ->first();

        $payload = array_filter([
            'name' => $client->legal_name ?: $client->name,
            'email' => $email,
            'cpfCnpj' => $document,
            'phone' => preg_replace('/\D+/', '', (string) $client->phone) ?: null,
            'mobilePhone' => preg_replace('/\D+/', '', (string) $client->phone) ?: null,
            'address' => $client->street,
            'addressNumber' => $client->number,
            'complement' => $client->complement,
            'province' => $client->neighborhood,
            'postalCode' => preg_replace('/\D+/', '', (string) $client->zip) ?: null,
            'externalReference' => $client->uuid,
        ], static fn ($value) => $value !== null && $value !== '');

        $asaas = $this->client();

        if ($cached) {
            try {
                $asaas->updateCustomer($cached->external_customer_id, $payload);

                return new GatewayCustomerDTO($cached->external_customer_id);
            } catch (AsaasException) {
                // recreate below
            }
        }

        $response = $asaas->createCustomer($payload);
        $externalId = (string) ($response['id'] ?? '');

        if ($externalId === '') {
            throw new AsaasException('O gateway não retornou o ID do cliente.');
        }

        FinanceGatewayCustomer::query()->updateOrCreate(
            [
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->getKey(),
                'gateway' => $this->key(),
            ],
            ['external_customer_id' => $externalId],
        );

        return new GatewayCustomerDTO($externalId);
    }

    public function createPayment(CreateGatewayPaymentDTO $payment): GatewayPaymentDTO
    {
        $payload = [
            'customer' => $payment->customerExternalId,
            'billingType' => match ($payment->paymentMethod) {
                PaymentMethod::PIX => 'PIX',
                PaymentMethod::BOLETO => 'BOLETO',
                PaymentMethod::CREDIT_CARD => 'CREDIT_CARD',
            },
            'value' => (float) $payment->amount,
            'dueDate' => $payment->dueDate->format('Y-m-d'),
            'description' => $payment->description,
            'externalReference' => $payment->externalReference,
        ];

        if ($payment->creditCard !== null) {
            $payload['creditCard'] = [
                'holderName' => $payment->creditCard->holderName,
                'number' => $payment->creditCard->number,
                'expiryMonth' => $payment->creditCard->expirationMonth,
                'expiryYear' => $payment->creditCard->expirationYear,
                'ccv' => $payment->creditCard->cvv,
            ];
            if ($payment->creditCard->holderInfo !== null) {
                $payload['creditCardHolderInfo'] = $payment->creditCard->holderInfo;
            }
        }

        $asaas = $this->client();
        $created = $asaas->createPayment($payload);
        $dto = AsaasPaymentMapper::toGatewayPayment($created);

        if ($payment->paymentMethod === PaymentMethod::PIX && $dto->externalId !== '') {
            try {
                $pix = $asaas->getPaymentPixQrCode($dto->externalId);

                return new GatewayPaymentDTO(
                    externalId: $dto->externalId,
                    status: $dto->status,
                    amount: $dto->amount,
                    externalReference: $dto->externalReference,
                    pixCode: isset($pix['payload']) ? (string) $pix['payload'] : $dto->pixCode,
                    pixQrcode: isset($pix['encodedImage']) ? (string) $pix['encodedImage'] : $dto->pixQrcode,
                    invoiceUrl: $dto->invoiceUrl,
                    bankSlipUrl: $dto->bankSlipUrl,
                    metadata: array_merge($dto->metadata ?? [], ['pix' => $pix]),
                );
            } catch (AsaasException) {
                return $dto;
            }
        }

        return $dto;
    }

    public function getPayment(string $externalPaymentId): GatewayPaymentDTO
    {
        return AsaasPaymentMapper::toGatewayPayment(
            $this->client()->getPayment($externalPaymentId),
        );
    }

    public function authenticateWebhook(Request $request): bool
    {
        $expected = (string) ($this->config()?->credential('webhook_token') ?? '');
        $provided = $request->header('asaas-access-token')
            ?? $request->header('Asaas-Access-Token')
            ?? $request->query('token');

        return filled($expected)
            && is_string($provided)
            && hash_equals($expected, $provided);
    }

    public function parseWebhook(Request $request): GatewayWebhookEventDTO
    {
        $payload = $request->all();
        $event = (string) ($payload['event'] ?? '');
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];

        $type = match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => GatewayWebhookEventType::PAYMENT_PAID,
            'PAYMENT_OVERDUE' => GatewayWebhookEventType::PAYMENT_OVERDUE,
            'PAYMENT_DELETED' => GatewayWebhookEventType::PAYMENT_DELETED,
            'PAYMENT_REFUNDED', 'PAYMENT_PARTIALLY_REFUNDED' => GatewayWebhookEventType::PAYMENT_REFUNDED,
            default => GatewayWebhookEventType::IGNORED,
        };

        return new GatewayWebhookEventDTO(
            type: $type,
            eventId: isset($payload['id']) ? (string) $payload['id'] : null,
            paymentExternalId: isset($payment['id']) ? (string) $payment['id'] : null,
            billingExternalReference: isset($payment['externalReference']) ? (string) $payment['externalReference'] : null,
            payload: $payload,
        );
    }

    private function client(): AsaasClient
    {
        $config = $this->config();
        $apiKey = (string) ($config?->credential('api_key') ?? '');
        $environment = (string) ($config?->credential('environment', 'sandbox'));
        $baseUrl = self::ENVIRONMENTS[$environment] ?? self::ENVIRONMENTS['sandbox'];

        return new AsaasClient($baseUrl, $apiKey);
    }

    private function config(): ?TenantPaymentGatewayConfig
    {
        return TenantPaymentGatewayConfig::query()
            ->where('gateway', $this->key())
            ->first();
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
