<?php

namespace App\Modules\Billing\Gateways\Asaas;

use Illuminate\Http\Client\Response;
use RuntimeException;

class AsaasException extends RuntimeException
{
    /**
     * @param  list<array{code?: string, description?: string}>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array $errors = [],
        public readonly ?array $body = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response, string $fallback = 'Falha na comunicação com o Asaas.'): self
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

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }
}
