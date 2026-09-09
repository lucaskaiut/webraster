<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;

class FinanceDashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_DASHBOARD_READ);
    }
}
