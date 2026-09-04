<?php

namespace App\Modules\ApiToken\Http\Middleware;

use App\Modules\ApiToken\Services\ApiTokenService;
use App\Modules\Tenant\Support\CurrentTenant;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica via API token customizado (api_*) ou delega ao Sanctum.
 *
 * - Se o header Authorization contiver token com prefixo "api_", autentica
 *   pelo módulo ApiToken e pula o guard Sanctum.
 * - Caso contrário, delega para o middleware auth:sanctum padrão (session
 *   cookie ou personal access token do Sanctum).
 */
class MultiAuthenticate
{
    public function __construct(
        private readonly ApiTokenService $service,
        private readonly CurrentTenant $context,
    ) {}

    public function handle(Request $request, Closure $next, string ...$guards): Response
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

        return app(Authenticate::class)->handle($request, $next, ...$guards);
    }
}
