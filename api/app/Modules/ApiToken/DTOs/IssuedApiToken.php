<?php

namespace App\Modules\ApiToken\DTOs;

use App\Modules\ApiToken\Models\ApiToken;

final readonly class IssuedApiToken
{
    public function __construct(
        public ApiToken $apiToken,
        public string $plainTextToken,
    ) {}
}
