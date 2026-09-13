<?php

namespace App\Modules\VehicleData\Exceptions;

use RuntimeException;

class PlateLookupException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 500,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self('Consulta de placa indisponível: provedor não configurado.', 503);
    }

    public static function invalidResponse(): self
    {
        return new self('O provedor retornou uma resposta inválida.', 502);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromHttpStatus(int $status, ?array $payload = null): self
    {
        $message = is_string($payload['message'] ?? null) ? trim($payload['message']) : '';

        if ($message === '') {
            $message = match ($status) {
                400 => 'URL incorreta!',
                401 => 'Placa inválida! Use o formato AAA0X00 ou AAA9999.',
                402 => 'Token inválido!',
                406 => 'Sem resultados para a placa informada.',
                429 => 'Limite de consultas atingido!',
                default => 'Falha na consulta de dados do veículo.',
            };
        }

        return new self($message, $status);
    }
}
