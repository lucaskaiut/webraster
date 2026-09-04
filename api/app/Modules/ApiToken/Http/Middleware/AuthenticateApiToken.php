<?php

namespace App\Modules\ApiToken\Http\Middleware;

use App\Modules\ApiToken\Services\ApiTokenService;
use App\Modules\Tenant\Support\CurrentTenant;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(
        private readonly ApiTokenService $service,
        private readonly CurrentTenant $context,
    ) {}

    /**
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $this->service->looksLikeApiToken($bearer)) {
            throw new AuthenticationException;
        }

        $apiToken = $this->service->findValidByPlainToken((string) $bearer);

        if ($apiToken === null) {
            throw new AuthenticationException;
        }

        $this->service->markAsUsed($apiToken);
        $this->context->set($apiToken->tenant);

        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
