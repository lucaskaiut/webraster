<?php

namespace App\Modules\Service\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SERVICE_READ);
    }

    public function view(User $user, Service $service): bool
    {
        return $this->sameTenant($service) && $user->hasPermission(Permission::SERVICE_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SERVICE_CREATE);
    }

    public function update(User $user, Service $service): bool
    {
        return $this->sameTenant($service) && $user->hasPermission(Permission::SERVICE_UPDATE);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->sameTenant($service) && $user->hasPermission(Permission::SERVICE_DELETE);
    }

    private function sameTenant(Service $service): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $service->tenant_id);
    }
}
