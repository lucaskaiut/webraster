<?php

namespace App\Modules\Billing\DTOs;

final readonly class GatewayCustomerDTO
{
    public function __construct(
        public string $externalId,
        public string $name,
        public string $email,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array{external_id: string, name: string, email: string, metadata?: ?array}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['external_id'],
            name: $data['name'],
            email: $data['email'],
            metadata: $data['metadata'] ?? null,
        );
    }
}
