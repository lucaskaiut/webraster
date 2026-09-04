<?php

namespace App\Modules\Tenant\Support;

use App\Modules\Tenant\Support\Facades\TenantContext;

/**
 * Helpers de autorização multi-tenant compartilhados por Policies.
 *
 * Compara o recurso com o tenant ativo (resolvido via TenantResolver),
 * nunca com o tenant "home" do usuário — assim masters operando filhos
 * passam na checagem de isolamento.
 */
final class TenantAuthorization
{
    public static function matchesCurrentTenant(int $resourceTenantId): bool
    {
        if (! TenantContext::isResolved()) {
            return false;
        }

        return TenantContext::tenantId() === $resourceTenantId;
    }
}
