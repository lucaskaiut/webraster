<?php

namespace App\Modules\Contract\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Contract\Models\Contract;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CONTRACT_READ);
    }

    public function view(User $user, Contract $contract): bool
    {
        return $this->sameTenant($contract) && $user->hasPermission(Permission::CONTRACT_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CONTRACT_CREATE);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->sameTenant($contract) && $user->hasPermission(Permission::CONTRACT_UPDATE);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->sameTenant($contract) && $user->hasPermission(Permission::CONTRACT_DELETE);
    }

    private function sameTenant(Contract $contract): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $contract->tenant_id);
    }
}
