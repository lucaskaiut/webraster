<?php

namespace App\Integrations\Traccar\DTOs;

final readonly class TraccarCommandResult
{
    /**
     * @param  array<string, mixed>|null  $body
     */
    public function __construct(
        public bool $successful,
        public int $status,
        public ?array $body,
        public ?string $rawBody = null,
    ) {}
}
