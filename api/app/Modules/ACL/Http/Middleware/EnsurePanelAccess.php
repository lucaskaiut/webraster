<?php

namespace App\Modules\ACL\Http\Middleware;

use App\Modules\ACL\Enums\Panel;
use App\Modules\User\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia o uso de um painel por usuários sem a permissão de acesso a ele.
 * Roda em todo o grupo "api": como a sessão é compartilhada entre os
 * painéis, não basta checar no login.
 */
class EnsurePanelAccess
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Panel::fromRequest($request);

        if ($panel === null) {
            return $next($request);
        }

        $user = $request->user('sanctum');

        if ($user instanceof User && ! $user->hasPermission($panel->permission())) {
            throw new AuthorizationException($panel->deniedMessage());
        }

        return $next($request);
    }
}
