<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class FinancePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_PLAN_READ);
    }

    public function view(User $user, FinancePlan $plan): bool
    {
        return $this->sameTenant($plan) && $user->hasPermission(Permission::FINANCE_PLAN_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_PLAN_CREATE);
    }

    public function update(User $user, FinancePlan $plan): bool
    {
        return $this->sameTenant($plan) && $user->hasPermission(Permission::FINANCE_PLAN_UPDATE);
    }

    public function delete(User $user, FinancePlan $plan): bool
    {
        return $this->sameTenant($plan) && $user->hasPermission(Permission::FINANCE_PLAN_DELETE);
    }

    private function sameTenant(FinancePlan $plan): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $plan->tenant_id);
    }
}
