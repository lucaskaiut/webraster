<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class FinanceReceivablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_RECEIVABLE_READ)
            || $user->hasPermission(Permission::FINANCE_PORTAL_VIEW);
    }

    public function view(User $user, FinanceReceivable $receivable): bool
    {
        return $this->sameTenant($receivable)
            && ClientAuthorization::allowsClient((int) $receivable->client_id)
            && (
                $user->hasPermission(Permission::FINANCE_RECEIVABLE_READ)
                || $user->hasPermission(Permission::FINANCE_PORTAL_VIEW)
            );
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_RECEIVABLE_CREATE);
    }

    public function update(User $user, FinanceReceivable $receivable): bool
    {
        return $this->sameTenant($receivable)
            && ClientAuthorization::allowsClient((int) $receivable->client_id)
            && $user->hasPermission(Permission::FINANCE_RECEIVABLE_UPDATE);
    }

    public function charge(User $user, FinanceReceivable $receivable): bool
    {
        return $this->sameTenant($receivable)
            && ClientAuthorization::allowsClient((int) $receivable->client_id)
            && (
                $user->hasPermission(Permission::FINANCE_RECEIVABLE_CHARGE)
                || $user->hasPermission(Permission::FINANCE_PORTAL_VIEW)
            );
    }

    public function cancel(User $user, FinanceReceivable $receivable): bool
    {
        return $this->update($user, $receivable);
    }

    public function pay(User $user, FinanceReceivable $receivable): bool
    {
        return $this->charge($user, $receivable);
    }

    private function sameTenant(FinanceReceivable $receivable): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $receivable->tenant_id);
    }
}
