<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class FinanceBillingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_BILLING_READ)
            || $user->hasPermission(Permission::FINANCE_PORTAL_VIEW);
    }

    public function view(User $user, FinanceBilling $billing): bool
    {
        return $this->sameTenant($billing)
            && ClientAuthorization::allowsClient((int) $billing->client_id)
            && (
                $user->hasPermission(Permission::FINANCE_BILLING_READ)
                || $user->hasPermission(Permission::FINANCE_PORTAL_VIEW)
            );
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_BILLING_CREATE);
    }

    public function update(User $user, FinanceBilling $billing): bool
    {
        return $this->sameTenant($billing)
            && ClientAuthorization::allowsClient((int) $billing->client_id)
            && $user->hasPermission(Permission::FINANCE_BILLING_UPDATE);
    }

    public function charge(User $user, FinanceBilling $billing): bool
    {
        return $this->sameTenant($billing)
            && ClientAuthorization::allowsClient((int) $billing->client_id)
            && (
                $user->hasPermission(Permission::FINANCE_BILLING_CHARGE)
                || $user->hasPermission(Permission::FINANCE_PORTAL_VIEW)
            );
    }

    public function cancel(User $user, FinanceBilling $billing): bool
    {
        return $this->update($user, $billing);
    }

    public function pay(User $user, FinanceBilling $billing): bool
    {
        return $this->charge($user, $billing);
    }

    private function sameTenant(FinanceBilling $billing): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $billing->tenant_id);
    }
}
