<?php

namespace App\Modules\Finance\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;

class FinanceCollectionSettingPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_COLLECTION_CONFIG_READ);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(Permission::FINANCE_COLLECTION_CONFIG_UPDATE);
    }
}
