<?php

namespace App\Modules\Finance\DTOs;

final readonly class GatewayCustomerDTO
{
    public function __construct(
        public string $externalId,
    ) {}
}
