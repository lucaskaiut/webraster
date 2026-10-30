<?php

namespace App\Modules\Tenant\Http\Middleware;

use App\Modules\Shared\Http\ApiError;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige um módulo liberado para o tenant em contexto. Usado em rotas
 * protegidas apenas por permissões do núcleo (ex.: abas do cliente) ou sem
 * permissão; as demais já são barradas pelo filtro de permissões.
 * Aceita alternativas separadas por "|": basta uma.
 */
class EnsureTenantModule
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $modules): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = TenantContext::tenant();

        if ($tenant === null) {
            return ApiError::response('Tenant não resolvido.', 404);
        }

        foreach (explode('|', $modules) as $module) {
            if ($tenant->hasModule(TenantModule::from($module))) {
                return $next($request);
            }
        }

        return ApiError::response('Este módulo não está habilitado para a sua empresa.', 403);
    }
}
