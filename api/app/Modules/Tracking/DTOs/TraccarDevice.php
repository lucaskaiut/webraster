<?php

namespace App\Modules\Tracking\DTOs;

readonly class TraccarDevice
{
    public function __construct(
        public int $id,
        public string $uniqueId,
        public string $name,
        public ?string $status = null,
        public ?int $positionId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            id: (int) $payload['id'],
            uniqueId: (string) $payload['uniqueId'],
            name: (string) ($payload['name'] ?? $payload['uniqueId']),
            status: isset($payload['status']) ? (string) $payload['status'] : null,
            positionId: isset($payload['positionId']) ? (int) $payload['positionId'] : null,
        );
    }
}
