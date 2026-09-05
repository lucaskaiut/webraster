<?php

namespace App\Modules\Equipment\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::EQUIPMENT_READ);
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $this->sameTenant($equipment)
            && $this->allowsAssignedClient($equipment)
            && $user->hasPermission(Permission::EQUIPMENT_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::EQUIPMENT_CREATE);
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $this->sameTenant($equipment)
            && $this->allowsAssignedClient($equipment)
            && $user->hasPermission(Permission::EQUIPMENT_UPDATE);
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $this->sameTenant($equipment)
            && $this->allowsAssignedClient($equipment)
            && $user->hasPermission(Permission::EQUIPMENT_DELETE);
    }

    private function sameTenant(Equipment $equipment): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $equipment->tenant_id);
    }

    private function allowsAssignedClient(Equipment $equipment): bool
    {
        $equipment->loadMissing('vehicle');

        if ($equipment->vehicle === null) {
            return ClientAuthorization::allowsClient(null);
        }

        return ClientAuthorization::allowsClient((int) $equipment->vehicle->client_id);
    }
}
