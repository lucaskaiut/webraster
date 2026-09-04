<?php

namespace App\Modules\Tenant\Resolution\Strategies;

use App\Modules\ApiToken\Services\ApiTokenService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Resolution\Contracts\ResolutionStrategy;
use Illuminate\Http\Request;

class ApiTokenStrategy implements ResolutionStrategy
{
    public function __construct(private readonly ApiTokenService $apiTokens) {}

    public function resolve(Request $request): ?Tenant
    {
        $bearer = $request->bearerToken();

        if (! $this->apiTokens->looksLikeApiToken($bearer)) {
            return null;
        }

        return $this->apiTokens->findValidByPlainToken((string) $bearer)?->tenant;
    }
}
