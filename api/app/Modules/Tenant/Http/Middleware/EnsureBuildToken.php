<?php

namespace App\Modules\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege endpoints usados por builds (CI/EAS) com um token estático
 * definido em `services.build.token` (env BUILD_TOKEN).
 */
class EnsureBuildToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.build.token');

        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            abort(401, 'Token de build inválido ou ausente.');
        }

        return $next($request);
    }
}
