<?php

namespace App\Modules\Auth\DTOs;

final readonly class NewTenantData
{
    public function __construct(
        public string $name,
        public string $document,
        public string $email,
        public ?string $phone,
    ) {}

    /**
     * @param  array{name: string, document: string, email: string, phone?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            document: $data['document'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
        );
    }

    /**
     * @return array{name: string, document: string, email: string, phone: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
