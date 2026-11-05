<?php

namespace App\Modules\ExternalApi\Services;

use App\Modules\ExternalApi\Models\ExternalApiRequest;
use App\Modules\ExternalApi\Support\ExternalApiCatalog;
use App\Modules\ExternalApi\Support\RequestOrigin;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava as chamadas do client HTTP destinadas a APIs do catálogo.
 *
 * Nunca interrompe a chamada original: qualquer falha ao gravar é apenas
 * registrada no log.
 */
class ExternalApiRecorder
{
    private const REDACTED = '[redacted]';

    public function __construct(
        private readonly ExternalApiCatalog $catalog,
        private readonly RequestOrigin $origin,
    ) {}

    public function handleResponse(ResponseReceived $event): void
    {
        $response = $event->response;
        $seconds = $response->transferStats?->getTransferTime();

        $this->record($event->request, [
            'status_code' => $response->status(),
            'succeeded' => $response->successful(),
            'duration_ms' => is_numeric($seconds) ? (int) round(((float) $seconds) * 1000) : null,
            'response_headers' => $this->headers($response->headers()),
            'response_body' => $this->body($response->body()),
        ]);
    }

    public function handleConnectionFailed(ConnectionFailed $event): void
    {
        $this->record($event->request, [
            'status_code' => null,
            'succeeded' => false,
            'error' => Str::limit($event->exception->getMessage(), 490),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(Request $request, array $attributes): void
    {
        if (! config('external_apis.logging.enabled', true)) {
            return;
        }

        try {
            $service = $this->catalog->match($request->url());

            if ($service === null) {
                return;
            }

            ExternalApiRequest::query()->create([
                ...$attributes,
                'provider' => $service['provider'],
                'service' => $service['key'],
                'method' => mb_strtoupper($request->method()),
                'url' => $this->url($request->url()),
                'request_headers' => $this->headers($request->headers()),
                'request_body' => $this->body($request->body()),
                'origin' => Str::limit((string) $this->origin->describe(), 250, ''),
                'tenant_id' => TenantContext::isResolved() ? TenantContext::tenantId() : null,
                'user_id' => $this->userId(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Falha ao registrar chamada de API externa', ['error' => $exception->getMessage()]);
        }
    }

    private function userId(): ?int
    {
        try {
            $id = Auth::hasUser() ? Auth::id() : null;

            return is_numeric($id) ? (int) $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Remove credenciais da query string.
     */
    private function url(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $url;
        }

        $redact = array_map('mb_strtolower', (array) config('external_apis.redact.query', []));
        $parts = [];

        foreach (explode('&', $query) as $pair) {
            [$name] = explode('=', $pair, 2);
            $parts[] = in_array(mb_strtolower(urldecode($name)), $redact, true)
                ? $name.'='.self::REDACTED
                : $pair;
        }

        return Str::before($url, '?').'?'.implode('&', $parts);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        $redact = array_map('mb_strtolower', (array) config('external_apis.redact.headers', []));
        $result = [];

        foreach ($headers as $name => $values) {
            $name = (string) $name;
            $value = implode(', ', array_map('strval', (array) $values));

            $result[$name] = in_array(mb_strtolower($name), $redact, true) ? self::REDACTED : $value;
        }

        return $result;
    }

    private function body(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $limit = max(1024, (int) config('external_apis.logging.max_body_bytes', 65536));

        if (strlen($body) <= $limit) {
            return $this->utf8($body);
        }

        return $this->utf8(mb_strcut($body, 0, $limit)).sprintf("\n… [truncado: %d bytes no total]", strlen($body));
    }

    private function utf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : '[conteúdo binário: '.strlen($value).' bytes]';
    }
}
