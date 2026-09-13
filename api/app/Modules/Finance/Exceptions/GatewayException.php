<?php

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

class GatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array $errors = [],
        public readonly ?array $body = null,
    ) {
        parent::__construct($message);
    }
}
