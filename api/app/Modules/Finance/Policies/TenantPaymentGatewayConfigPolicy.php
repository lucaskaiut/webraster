<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Finance\Models\TenantPaymentGatewayConfig;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class TenantPaymentGatewayConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_GATEWAY_CONFIG_READ);
    }

    public function view(User $user, TenantPaymentGatewayConfig $config): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $config->tenant_id)
            && $user->hasPermission(Permission::FINANCE_GATEWAY_CONFIG_READ);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_GATEWAY_CONFIG_UPDATE);
    }
}
