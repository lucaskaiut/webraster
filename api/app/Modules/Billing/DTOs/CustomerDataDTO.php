<?php

namespace App\Modules\Billing\DTOs;

final readonly class CustomerDataDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $document,
        public ?string $phone = null,
        public ?string $externalId = null,
    ) {}

    /**
     * @param  array{name: string, email: string, document: string, phone?: ?string, external_id?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            document: $data['document'],
            phone: $data['phone'] ?? null,
            externalId: $data['external_id'] ?? null,
        );
    }

    /**
     * @return array{name: string, email: string, document: string, phone: ?string, external_id: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'document' => $this->document,
            'phone' => $this->phone,
            'external_id' => $this->externalId,
        ];
    }
}
