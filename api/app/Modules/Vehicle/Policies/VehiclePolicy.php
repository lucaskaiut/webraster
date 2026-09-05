<?php

namespace App\Modules\Vehicle\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VEHICLE_READ);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->sameTenant($vehicle)
            && ClientAuthorization::allowsClient((int) $vehicle->client_id)
            && $user->hasPermission(Permission::VEHICLE_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::VEHICLE_CREATE);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->sameTenant($vehicle)
            && ClientAuthorization::allowsClient((int) $vehicle->client_id)
            && $user->hasPermission(Permission::VEHICLE_UPDATE);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->sameTenant($vehicle)
            && ClientAuthorization::allowsClient((int) $vehicle->client_id)
            && $user->hasPermission(Permission::VEHICLE_DELETE);
    }

    private function sameTenant(Vehicle $vehicle): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $vehicle->tenant_id);
    }
}
