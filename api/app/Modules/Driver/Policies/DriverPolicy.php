<?php

namespace App\Modules\Driver\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Driver\Models\Driver;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::DRIVER_READ);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $this->sameTenant($driver)
            && ClientAuthorization::allowsClient((int) $driver->client_id)
            && $user->hasPermission(Permission::DRIVER_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::DRIVER_CREATE);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $this->sameTenant($driver)
            && ClientAuthorization::allowsClient((int) $driver->client_id)
            && $user->hasPermission(Permission::DRIVER_UPDATE);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->sameTenant($driver)
            && ClientAuthorization::allowsClient((int) $driver->client_id)
            && $user->hasPermission(Permission::DRIVER_DELETE);
    }

    private function sameTenant(Driver $driver): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $driver->tenant_id);
    }
}
