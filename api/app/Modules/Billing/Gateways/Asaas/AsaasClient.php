<?php

namespace App\Modules\Billing\Gateways\Asaas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP compartilhado da API Asaas.
 * Credenciais: config('asaas.*') — usadas por PIX, cartão e demais métodos.
 */
final class AsaasClient
{
    public function createCustomer(array $payload): array
    {
        return $this->request('post', '/customers', $payload);
    }

    public function updateCustomer(string $customerId, array $payload): array
    {
        return $this->request('put', '/customers/'.$customerId, $payload);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{data?: list<array<string, mixed>>, totalCount?: int}
     */
    public function listCustomers(array $query = []): array
    {
        return $this->request('get', '/customers', query: $query);
    }

    public function createPayment(array $payload, ?int $timeout = null): array
    {
        return $this->request(
            'post',
            '/payments',
            $payload,
            timeout: $timeout ?? (int) config('asaas.credit_card_timeout', 60),
        );
    }

    public function getPayment(string $paymentId): array
    {
        return $this->request('get', '/payments/'.$paymentId);
    }

    public function deletePayment(string $paymentId): array
    {
        return $this->request('delete', '/payments/'.$paymentId);
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
        ?int $timeout = null,
    ): array {
        $response = $this->http($timeout)
            ->{$method}($this->url($path), $method === 'get' ? $query : ($payload ?? []));

        if ($response->failed()) {
            throw AsaasException::fromResponse($response);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function http(?int $timeout = null): PendingRequest
    {
        $apiKey = (string) config('asaas.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('ASAAS_API_KEY não configurada.');
        }

        $resolvedTimeout = $timeout ?? (int) config('asaas.timeout', 30);

        return Http::baseUrl(rtrim((string) config('asaas.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' => $apiKey,
                'User-Agent' => (string) config('asaas.user_agent', 'NoxConnect/1.0'),
            ])
            ->timeout(max(1, $resolvedTimeout))
            ->connectTimeout(10);
    }

    private function url(string $path): string
    {
        return '/'.ltrim($path, '/');
    }
}
