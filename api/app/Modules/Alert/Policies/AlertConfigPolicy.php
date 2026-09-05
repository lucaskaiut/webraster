<?php

namespace App\Modules\Alert\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class AlertConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ALERT_CONFIG_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ALERT_CONFIG_UPDATE);
    }

    public function update(User $user, AlertConfig $config): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $config->tenant_id)
            && $user->hasPermission(Permission::ALERT_CONFIG_UPDATE);
    }

    public function delete(User $user, AlertConfig $config): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $config->tenant_id)
            && $user->hasPermission(Permission::ALERT_CONFIG_UPDATE);
    }
}
