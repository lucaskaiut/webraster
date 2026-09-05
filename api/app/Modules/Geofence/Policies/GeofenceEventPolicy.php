<?php

namespace App\Modules\Geofence\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Geofence\Models\GeofenceEvent;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class GeofenceEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::GEOFENCE_READ);
    }

    public function view(User $user, GeofenceEvent $event): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $event->tenant_id)
            && ClientAuthorization::allowsClient((int) $event->client_id)
            && $user->hasPermission(Permission::GEOFENCE_READ);
    }
}
