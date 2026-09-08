<?php

namespace App\Modules\DeviceCommand\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class DeviceCommandPolicy
{
    public function viewCommands(User $user, Equipment $equipment): bool
    {
        return $this->canAccessDevice($user, $equipment)
            && $user->hasPermission(Permission::DEVICE_COMMANDS_SEND);
    }

    public function send(User $user, Equipment $equipment): bool
    {
        return $this->canAccessDevice($user, $equipment)
            && $user->hasPermission(Permission::DEVICE_COMMANDS_SEND);
    }

    private function canAccessDevice(User $user, Equipment $equipment): bool
    {
        if (! TenantAuthorization::matchesCurrentTenant((int) $equipment->tenant_id)) {
            return false;
        }

        $equipment->loadMissing('vehicle');

        if ($equipment->vehicle === null) {
            return ClientAuthorization::allowsClient(null);
        }

        return ClientAuthorization::allowsClient((int) $equipment->vehicle->client_id);
    }
}
