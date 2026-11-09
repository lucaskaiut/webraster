<?php

namespace App\Integrations\Vercel;

use Illuminate\Http\Client\Response;
use RuntimeException;

class VercelException extends RuntimeException
{
    public static function fromResponse(string $action, Response $response): self
    {
        $error = $response->json('error');
        $detail = is_array($error)
            ? trim(($error['code'] ?? '').': '.($error['message'] ?? ''), ': ')
            : mb_substr($response->body(), 0, 300);

        return new self("Vercel: falha ao {$action} (HTTP {$response->status()}) {$detail}");
    }
}
