<?php

namespace App\Modules\Finance\Gateways\Asaas;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceAsaasCustomer;
use App\Modules\Finance\Models\TenantAsaasConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class AsaasClient
{
    public function __construct(
        private readonly TenantAsaasConfig $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createOrUpdateCustomer(Client $client): string
    {
        $cached = FinanceAsaasCustomer::query()
            ->where('client_id', $client->getKey())
            ->first();

        $payload = [
            'name' => $client->legal_name ?: $client->name,
            'email' => $client->financial_email ?: $client->email,
            'cpfCnpj' => preg_replace('/\D+/', '', (string) $client->document) ?: null,
            'phone' => preg_replace('/\D+/', '', (string) $client->phone) ?: null,
            'mobilePhone' => preg_replace('/\D+/', '', (string) $client->phone) ?: null,
            'address' => $client->street,
            'addressNumber' => $client->number,
            'complement' => $client->complement,
            'province' => $client->neighborhood,
            'postalCode' => preg_replace('/\D+/', '', (string) $client->zip) ?: null,
            'externalReference' => $client->uuid,
        ];

        $payload = array_filter($payload, static fn ($value) => $value !== null && $value !== '');

        if ($cached) {
            try {
                $this->request('put', '/customers/'.$cached->asaas_customer_id, $payload);

                return $cached->asaas_customer_id;
            } catch (AsaasException) {
                // recreate below
            }
        }

        $response = $this->request('post', '/customers', $payload);
        $asaasId = (string) ($response['id'] ?? '');

        if ($asaasId === '') {
            throw new AsaasException('Asaas não retornou o ID do cliente.');
        }

        FinanceAsaasCustomer::query()->updateOrCreate(
            [
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->getKey(),
            ],
            ['asaas_customer_id' => $asaasId],
        );

        return $asaasId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        return $this->request('post', '/payments', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $id): array
    {
        return $this->request('get', '/payments/'.$id);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentPixQrCode(string $paymentId): array
    {
        return $this->request('get', '/payments/'.$paymentId.'/pixQrCode');
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        array $query = [],
    ): array {
        $response = $this->http()
            ->{$method}($this->url($path), $method === 'get' ? $query : ($payload ?? []));

        if ($response->failed()) {
            throw AsaasException::fromResponse($response);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function http(): PendingRequest
    {
        $apiKey = (string) $this->config->api_key;

        if ($apiKey === '') {
            throw new AsaasException('API key Asaas não configurada para o tenant.');
        }

        return Http::baseUrl(rtrim($this->config->environment->baseUrl(), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' => $apiKey,
                'User-Agent' => 'WebRaster-Finance/1.0',
            ])
            ->timeout(30)
            ->connectTimeout(10);
    }

    private function url(string $path): string
    {
        return '/'.ltrim($path, '/');
    }
}
