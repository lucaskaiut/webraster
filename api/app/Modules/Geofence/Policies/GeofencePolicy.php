<?php

namespace App\Modules\Geofence\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class GeofencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::GEOFENCE_READ);
    }

    public function view(User $user, Geofence $geofence): bool
    {
        return $this->sameTenant($geofence)
            && ClientAuthorization::allowsClient((int) $geofence->client_id)
            && $user->hasPermission(Permission::GEOFENCE_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::GEOFENCE_CREATE);
    }

    public function update(User $user, Geofence $geofence): bool
    {
        return $this->sameTenant($geofence)
            && ClientAuthorization::allowsClient((int) $geofence->client_id)
            && $user->hasPermission(Permission::GEOFENCE_UPDATE);
    }

    public function delete(User $user, Geofence $geofence): bool
    {
        return $this->sameTenant($geofence)
            && ClientAuthorization::allowsClient((int) $geofence->client_id)
            && $user->hasPermission(Permission::GEOFENCE_DELETE);
    }

    private function sameTenant(Geofence $geofence): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $geofence->tenant_id);
    }
}
