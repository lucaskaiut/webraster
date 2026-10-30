<?php

namespace App\Modules\Billing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas de assinatura e faturas do umbrella só existem com a cobrança
 * ligada (config billing.enabled).
 */
class EnsureBillingEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('billing.enabled'), 404);

        return $next($request);
    }
}
