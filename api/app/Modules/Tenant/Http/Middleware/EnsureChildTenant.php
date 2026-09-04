<?php

namespace App\Modules\Tenant\Http\Middleware;

use App\Modules\Shared\Http\ApiError;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia funcionalidades de uso final no tenant umbrella (raiz).
 * Exige um tenant filho (empresa operacional) no contexto atual.
 */
class EnsureChildTenant
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! TenantContext::isResolved()) {
            return ApiError::response('Tenant não resolvido.', 404);
        }

        /** @var Tenant $tenant */
        $tenant = TenantContext::tenant();

        if ($tenant->isUmbrella()) {
            return ApiError::response(
                'Esta funcionalidade está disponível apenas em empresas filhas. Selecione um tenant operacional.',
                403,
            );
        }

        return $next($request);
    }
}
