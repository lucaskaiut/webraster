<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class FinanceContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_CONTRACT_READ);
    }

    public function view(User $user, FinanceContract $contract): bool
    {
        return $this->sameTenant($contract)
            && ClientAuthorization::allowsClient((int) $contract->client_id)
            && $user->hasPermission(Permission::FINANCE_CONTRACT_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_CONTRACT_CREATE);
    }

    public function update(User $user, FinanceContract $contract): bool
    {
        return $this->sameTenant($contract)
            && ClientAuthorization::allowsClient((int) $contract->client_id)
            && $user->hasPermission(Permission::FINANCE_CONTRACT_UPDATE);
    }

    public function delete(User $user, FinanceContract $contract): bool
    {
        return $this->sameTenant($contract)
            && ClientAuthorization::allowsClient((int) $contract->client_id)
            && $user->hasPermission(Permission::FINANCE_CONTRACT_DELETE);
    }

    public function changeStatus(User $user, FinanceContract $contract): bool
    {
        return $this->update($user, $contract);
    }

    private function sameTenant(FinanceContract $contract): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $contract->tenant_id);
    }
}
