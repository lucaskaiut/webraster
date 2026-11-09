<?php

namespace App\Integrations\Cloudflare;

use Illuminate\Http\Client\Response;
use RuntimeException;

class CloudflareException extends RuntimeException
{
    public static function fromResponse(string $action, Response $response): self
    {
        $detail = collect($response->json('errors') ?? [])
            ->map(fn ($error): string => is_array($error) ? trim(($error['code'] ?? '').' '.($error['message'] ?? '')) : (string) $error)
            ->implode('; ') ?: mb_substr($response->body(), 0, 300);

        return new self("Cloudflare: falha ao {$action} (HTTP {$response->status()}) {$detail}");
    }
}
