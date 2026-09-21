<?php

namespace App\Modules\Tracking\DTOs;

use Carbon\CarbonImmutable;

readonly class TraccarDevice
{
    public function __construct(
        public int $id,
        public string $uniqueId,
        public string $name,
        public ?string $status = null,
        public ?int $positionId = null,
        public ?CarbonImmutable $lastUpdate = null,
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
            lastUpdate: filled($payload['lastUpdate'] ?? null)
                ? CarbonImmutable::parse((string) $payload['lastUpdate'])
                : null,
        );
    }
}
