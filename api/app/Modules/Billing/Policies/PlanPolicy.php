<?php

namespace App\Modules\Billing\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

/**
 * Planos só podem ser gerenciados por tenants umbrella (sem parent_id).
 * A permissão ACL (plan.*) é necessária, mas insuficiente sem a hierarquia.
 */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManagePlans($user, Permission::PLAN_READ);
    }

    public function view(User $user, Plan $plan): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $plan->tenant_id)
            && $this->canManagePlans($user, Permission::PLAN_READ);
    }

    public function create(User $user): bool
    {
        return $this->canManagePlans($user, Permission::PLAN_CREATE);
    }

    public function update(User $user, Plan $plan): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $plan->tenant_id)
            && $this->canManagePlans($user, Permission::PLAN_UPDATE);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $plan->tenant_id)
            && $this->canManagePlans($user, Permission::PLAN_DELETE);
    }

    private function canManagePlans(User $user, Permission $permission): bool
    {
        return $user->hasPermission($permission) && $this->currentTenantIsUmbrella();
    }

    private function currentTenantIsUmbrella(): bool
    {
        if (! TenantContext::isResolved()) {
            return false;
        }

        /** @var Tenant $tenant */
        $tenant = TenantContext::tenant();

        return $tenant->isUmbrella();
    }
}
