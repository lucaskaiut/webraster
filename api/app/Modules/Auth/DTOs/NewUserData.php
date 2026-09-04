<?php

namespace App\Modules\Auth\DTOs;

final readonly class NewUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $document,
        public string $password,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, document?: ?string, password: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            document: $data['document'] ?? null,
            password: $data['password'],
        );
    }

    /**
     * @return array{name: string, email: string, phone: ?string, document: ?string, password: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'document' => $this->document,
            'password' => $this->password,
        ];
    }
}
