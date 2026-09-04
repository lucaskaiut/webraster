<?php

namespace App\Modules\Tenant\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Models\Tenant;
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

    public function viewChild(User $user, Tenant $child): bool
    {
        return $this->ownsChild($user, $child)
            && $user->hasPermission(Permission::TENANT_READ);
    }

    public function updateChild(User $user, Tenant $child): bool
    {
        return $this->ownsChild($user, $child)
            && $user->hasPermission(Permission::TENANT_UPDATE);
    }

    /**
     * Cadastro de tenants filhos é restrito ao usuário master do tenant
     * umbrella (raiz / sem parent_id), independente do X-Tenant-Id ativo.
     */
    private function isUmbrellaMaster(User $user): bool
    {
        if (! $user->is_master) {
            return false;
        }

        $home = $user->tenant;

        return $home !== null && $home->isUmbrella();
    }

    private function ownsChild(User $user, Tenant $child): bool
    {
        if (! $this->isUmbrellaMaster($user)) {
            return false;
        }

        $umbrella = $user->tenant;

        return $umbrella !== null
            && (int) $child->parent_id === (int) $umbrella->getKey();
    }
}
