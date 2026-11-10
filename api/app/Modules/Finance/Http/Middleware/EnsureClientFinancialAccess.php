<?php

namespace App\Modules\Finance\Http\Middleware;

use App\Modules\Finance\Exceptions\ClientFinanciallyBlocked;
use App\Modules\Finance\Services\ClientFinancialBlockService;
use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cliente bloqueado por inadimplência só usa o financeiro do portal e as
 * notificações. Roda depois de client.scope, que resolve o cliente.
 */
class EnsureClientFinancialAccess
{
    /** Rotas liberadas durante o bloqueio (padrões de Request::is). */
    public const ALLOWED_PATHS = [
        'api/finance/portal',
        'api/finance/portal/*',
        'api/notifications',
        'api/notifications/*',
    ];

    public function __construct(private readonly ClientFinancialBlockService $blocks) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $request->is(...self::ALLOWED_PATHS)) {
            return $next($request);
        }

        $status = $this->blocks->forUser($user);

        if ($status !== null && $status['blocked']) {
            throw ClientFinanciallyBlocked::make($status['message']);
        }

        return $next($request);
    }
}
