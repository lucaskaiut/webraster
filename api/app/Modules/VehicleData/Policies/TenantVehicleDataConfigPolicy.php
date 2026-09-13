<?php

namespace App\Modules\VehicleData\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;
use App\Modules\VehicleData\Models\TenantVehicleDataConfig;

class TenantVehicleDataConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VEHICLE_DATA_CONFIG_READ);
    }

    public function view(User $user, TenantVehicleDataConfig $config): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $config->tenant_id)
            && $user->hasPermission(Permission::VEHICLE_DATA_CONFIG_READ);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(Permission::VEHICLE_DATA_CONFIG_UPDATE);
    }
}
