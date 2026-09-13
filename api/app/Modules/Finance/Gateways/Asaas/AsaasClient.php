<?php

namespace App\Modules\Finance\Gateways\Asaas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class AsaasClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCustomer(array $payload): array
    {
        return $this->request('post', '/customers', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateCustomer(string $customerId, array $payload): array
    {
        return $this->request('put', '/customers/'.$customerId, $payload);
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
        if ($this->apiKey === '') {
            throw new AsaasException('API key do gateway não configurada para o tenant.');
        }

        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' => $this->apiKey,
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
