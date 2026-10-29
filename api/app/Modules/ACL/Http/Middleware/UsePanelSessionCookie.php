<?php

namespace App\Modules\ACL\Http\Middleware;

use App\Modules\ACL\Enums\Panel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usa um cookie de sessão por painel (header X-Panel). Precisa rodar antes
 * do StartSession, por isso é um middleware global.
 */
class UsePanelSessionCookie
{
    public function __construct(private readonly SessionManager $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $baseName = (string) config('session.cookie');

        $this->sessions->driver()->setName(
            Panel::fromRequest($request)?->sessionCookie($baseName) ?? $baseName,
        );

        return $next($request);
    }
}
