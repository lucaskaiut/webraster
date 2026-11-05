<?php

namespace App\Modules\ExternalApi\Http\Middleware;

use App\Modules\Shared\Http\ApiError;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe ao tenant umbrella (raiz) — dados de plataforma, como o consumo
 * de APIs externas, não pertencem a nenhuma empresa filha.
 */
class EnsureUmbrellaTenant
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = TenantContext::isResolved() ? TenantContext::tenant() : null;

        if ($tenant === null || ! $tenant->isUmbrella()) {
            return ApiError::response('Disponível apenas no tenant raiz (umbrella).', 403);
        }

        return $next($request);
    }
}
