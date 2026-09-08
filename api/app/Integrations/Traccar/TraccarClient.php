<?php

namespace App\Integrations\Traccar;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente HTTP genérico da API Traccar.
 * Sem regras de protocolo/fabricante — apenas transporte.
 */
class TraccarClient
{
    public function isConfigured(): bool
    {
        if (! config('traccar.enabled')) {
            return false;
        }

        if (blank(config('traccar.base_url'))) {
            return false;
        }

        return filled(config('traccar.token'))
            || (filled(config('traccar.email')) && filled(config('traccar.password')));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->client()->get($path, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $path, array $payload = []): Response
    {
        return $this->client()->asJson()->post($path, $payload);
    }

    public function fail(string $message, int $status, string $body): never
    {
        Log::warning('traccar.client_error', [
            'message' => $message,
            'status' => $status,
            'body' => mb_substr($body, 0, 500),
        ]);

        throw new RuntimeException($message);
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integração Traccar não configurada.');
        }

        $request = Http::baseUrl((string) config('traccar.base_url'))
            ->acceptJson()
            ->timeout((int) config('traccar.timeout', 15));

        $token = config('traccar.token');

        if (filled($token)) {
            return $request->withToken((string) $token);
        }

        return $request->withBasicAuth(
            (string) config('traccar.email'),
            (string) config('traccar.password'),
        );
    }
}
