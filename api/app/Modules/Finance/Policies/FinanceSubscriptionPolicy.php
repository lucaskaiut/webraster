<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class FinanceSubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_SUBSCRIPTION_READ)
            || $user->hasPermission(Permission::FINANCE_PORTAL_VIEW);
    }

    public function view(User $user, FinanceSubscription $subscription): bool
    {
        return $this->sameTenant($subscription)
            && ClientAuthorization::allowsClient((int) $subscription->client_id)
            && (
                $user->hasPermission(Permission::FINANCE_SUBSCRIPTION_READ)
                || $user->hasPermission(Permission::FINANCE_PORTAL_VIEW)
            );
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_SUBSCRIPTION_CREATE);
    }

    public function update(User $user, FinanceSubscription $subscription): bool
    {
        return $this->sameTenant($subscription)
            && ClientAuthorization::allowsClient((int) $subscription->client_id)
            && $user->hasPermission(Permission::FINANCE_SUBSCRIPTION_UPDATE);
    }

    public function cancel(User $user, FinanceSubscription $subscription): bool
    {
        return $this->update($user, $subscription);
    }

    public function reactivate(User $user, FinanceSubscription $subscription): bool
    {
        return $this->update($user, $subscription);
    }

    private function sameTenant(FinanceSubscription $subscription): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $subscription->tenant_id);
    }
}
