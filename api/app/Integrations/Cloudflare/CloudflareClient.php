<?php

namespace App\Integrations\Cloudflare;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API de DNS do Cloudflare.
 */
class CloudflareClient
{
    /** @var array<string, string> */
    private array $zoneIds = [];

    public function isConfigured(): bool
    {
        return filled(config('services.cloudflare.token'));
    }

    /**
     * Zona configurada (services.cloudflare.zone_id) ou localizada pelo nome.
     */
    public function zoneId(string $zoneName): string
    {
        $configured = config('services.cloudflare.zone_id');

        if (filled($configured)) {
            return (string) $configured;
        }

        if (isset($this->zoneIds[$zoneName])) {
            return $this->zoneIds[$zoneName];
        }

        $response = $this->send(fn () => $this->client()->get('/zones', ['name' => $zoneName]), "localizar a zona {$zoneName}");
        $id = $response->json('result.0.id');

        if (! is_string($id) || $id === '') {
            throw new CloudflareException("Cloudflare: zona {$zoneName} não encontrada para o token configurado.");
        }

        return $this->zoneIds[$zoneName] = $id;
    }

    /**
     * Registros DNS com o nome exato informado (todos os tipos).
     *
     * @return list<array<string, mixed>>
     */
    public function recordsNamed(string $zoneId, string $name): array
    {
        $response = $this->send(
            fn () => $this->client()->get("/zones/{$zoneId}/dns_records", ['name' => $name, 'per_page' => 100]),
            "consultar os registros de {$name}",
        );

        return array_values((array) $response->json('result', []));
    }

    /**
     * @param  array<string, mixed>  $record  type, name, content, ttl, proxied, comment
     * @return array<string, mixed>
     */
    public function createRecord(string $zoneId, array $record): array
    {
        $response = $this->send(
            fn () => $this->client()->post("/zones/{$zoneId}/dns_records", $record),
            "criar o registro {$record['type']} {$record['name']}",
        );

        return (array) $response->json('result');
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function updateRecord(string $zoneId, string $recordId, array $record): array
    {
        $response = $this->send(
            fn () => $this->client()->patch("/zones/{$zoneId}/dns_records/{$recordId}", $record),
            "atualizar o registro {$record['type']} {$record['name']}",
        );

        return (array) $response->json('result');
    }

    public function deleteRecord(string $zoneId, string $recordId): void
    {
        $response = $this->client()->delete("/zones/{$zoneId}/dns_records/{$recordId}");

        if ($response->failed() && ! $response->notFound()) {
            throw CloudflareException::fromResponse("remover o registro {$recordId}", $response);
        }
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request, string $action): Response
    {
        $response = $request();

        if ($response->failed() || $response->json('success') === false) {
            throw CloudflareException::fromResponse($action, $response);
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new CloudflareException('Integração Cloudflare não configurada (CLOUDFLARE_API_TOKEN).');
        }

        return Http::baseUrl((string) config('services.cloudflare.base_url'))
            ->withToken((string) config('services.cloudflare.token'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.cloudflare.timeout', 15));
    }
}
