<?php

namespace App\Modules\Tenant\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isUmbrellaMaster($user)
            && $user->hasPermission(Permission::TENANT_READ);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return TenantAuthorization::matchesCurrentTenant($tenant->getKey())
            && $user->hasPermission(Permission::TENANT_READ);
    }

    public function create(User $user): bool
    {
        return $this->isUmbrellaMaster($user)
            && $user->hasPermission(Permission::TENANT_CREATE);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return TenantAuthorization::matchesCurrentTenant($tenant->getKey())
            && $user->hasPermission(Permission::TENANT_UPDATE);
    }

    /**
     * Cadastro de tenants filhos é restrito ao usuário master do tenant
     * umbrella (raiz / sem parent_id).
     */
    private function isUmbrellaMaster(User $user): bool
    {
        if (! $user->is_master || ! TenantContext::isResolved()) {
            return false;
        }

        return TenantContext::tenant()?->isUmbrella() ?? false;
    }
}
