<?php

namespace App\Modules\ACL\Http\Middleware;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $apiToken = $request->attributes->get('api_token');
        $user = $request->user();

        $enum = Permission::tryFrom($permission);

        if ($enum === null) {
            throw new InvalidArgumentException("Permissão desconhecida [{$permission}].");
        }

        if ($apiToken instanceof ApiToken) {
            if (! $apiToken->can($permission)) {
                throw new AuthorizationException('Este token não possui escopo para executar esta ação.');
            }

            return $next($request);
        }

        if ($user !== null) {
            if (! $user->hasPermission($enum)) {
                throw new AuthorizationException('Você não possui permissão para executar esta ação.');
            }

            return $next($request);
        }

        throw new AuthenticationException;
    }
}
