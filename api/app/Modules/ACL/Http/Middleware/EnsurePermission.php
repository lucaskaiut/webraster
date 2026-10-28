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
     * Aceita uma permissão ou alternativas separadas por "|"
     * (ex.: "service-order.change-status|service-order.cancel"): basta uma.
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $apiToken = $request->attributes->get('api_token');
        $user = $request->user();

        $enums = array_map(function (string $value): Permission {
            return Permission::tryFrom($value)
                ?? throw new InvalidArgumentException("Permissão desconhecida [{$value}].");
        }, explode('|', $permission));

        if ($apiToken instanceof ApiToken) {
            $allowed = array_filter($enums, fn (Permission $enum) => $apiToken->can($enum->value));

            if ($allowed === []) {
                throw new AuthorizationException('Este token não possui escopo para executar esta ação.');
            }

            return $next($request);
        }

        if ($user !== null) {
            $allowed = array_filter($enums, fn (Permission $enum) => $user->hasPermission($enum));

            if ($allowed === []) {
                throw new AuthorizationException('Você não possui permissão para executar esta ação.');
            }

            return $next($request);
        }

        throw new AuthenticationException;
    }
}
