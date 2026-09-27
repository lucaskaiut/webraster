<?php

namespace App\Modules\Chat\DTOs;

final readonly class OutboundMessageResult
{
    public function __construct(
        public bool $success,
        public ?string $externalId = null,
        public ?string $error = null,
        public ?array $raw = null,
    ) {}
}
