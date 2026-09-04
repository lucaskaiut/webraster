<?php

namespace App\Modules\Billing\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SUBSCRIPTION_READ);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $subscription->tenant_id)
            && $user->hasPermission(Permission::SUBSCRIPTION_READ);
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $subscription->tenant_id)
            && $user->hasPermission(Permission::SUBSCRIPTION_UPDATE);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SUBSCRIPTION_UPDATE);
    }
}
