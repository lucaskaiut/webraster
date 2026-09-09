<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Finance\Models\TenantAsaasConfig;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class TenantAsaasConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_ASAAS_CONFIG_READ);
    }

    public function view(User $user, TenantAsaasConfig $config): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $config->tenant_id)
            && $user->hasPermission(Permission::FINANCE_ASAAS_CONFIG_READ);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_ASAAS_CONFIG_UPDATE);
    }
}
