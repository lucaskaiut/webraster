<?php

namespace App\Integrations\Traccar\DTOs;

final readonly class TraccarCommandType
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $type,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = (string) ($data['type'] ?? $data['id'] ?? '');

        return new self(
            type: $type,
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->raw !== []) {
            return array_merge($this->raw, ['type' => $this->type]);
        }

        return ['type' => $this->type];
    }
}
