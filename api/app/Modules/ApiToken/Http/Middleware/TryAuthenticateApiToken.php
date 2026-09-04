<?php

namespace App\Modules\ApiToken\Http\Middleware;

use App\Modules\ApiToken\Services\ApiTokenService;
use App\Modules\Tenant\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica via API token customizado (api_*) quando o header Authorization
 * contém um token com esse prefixo. Caso contrário, delega para o guard
 * Sanctum padrão (session cookie ou personal access token).
 */
class TryAuthenticateApiToken
{
    public function __construct(
        private readonly ApiTokenService $service,
        private readonly CurrentTenant $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($this->service->looksLikeApiToken($bearer)) {
            $apiToken = $this->service->findValidByPlainToken((string) $bearer);

            if ($apiToken === null) {
                abort(401, 'Token de API inválido ou expirado.');
            }

            $this->service->markAsUsed($apiToken);
            $this->context->set($apiToken->tenant);

            $request->attributes->set('api_token', $apiToken);

            return $next($request);
        }

        return $next($request);
    }
}
