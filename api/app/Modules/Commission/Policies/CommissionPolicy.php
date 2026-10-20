<?php

namespace App\Modules\Commission\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Commission\Models\Commission;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class CommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::COMMISSION_READ);
    }

    public function view(User $user, Commission $commission): bool
    {
        if (
            ! TenantAuthorization::matchesCurrentTenant((int) $commission->tenant_id)
            || ! $user->hasPermission(Permission::COMMISSION_READ)
        ) {
            return false;
        }

        return $user->hasPermission(Permission::COMMISSION_VIEW_OTHERS)
            || (int) $commission->technician_id === (int) $user->getKey();
    }
}
