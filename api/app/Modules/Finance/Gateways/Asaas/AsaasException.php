<?php

namespace App\Modules\Finance\Gateways\Asaas;

use App\Modules\Finance\Exceptions\GatewayException;
use Illuminate\Http\Client\Response;

class AsaasException extends GatewayException
{
    public static function fromResponse(Response $response, string $fallback = 'Falha na comunicação com o gateway de pagamento.'): self
    {
        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];

        $descriptions = array_values(array_filter(array_map(
            static fn (mixed $error): ?string => is_array($error)
                ? (isset($error['description']) ? (string) $error['description'] : null)
                : null,
            $errors,
        )));

        $message = $descriptions !== []
            ? implode(' ', $descriptions)
            : $fallback;

        return new self(
            message: $message,
            statusCode: $response->status(),
            errors: $errors,
            body: $body,
        );
    }
}
