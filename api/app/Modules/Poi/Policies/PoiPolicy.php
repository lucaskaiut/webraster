<?php

namespace App\Modules\Poi\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Poi\Models\Poi;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class PoiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::POI_READ);
    }

    public function view(User $user, Poi $poi): bool
    {
        return $this->sameTenant($poi)
            && ClientAuthorization::allowsClient((int) $poi->client_id)
            && $user->hasPermission(Permission::POI_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::POI_CREATE);
    }

    public function update(User $user, Poi $poi): bool
    {
        return $this->sameTenant($poi)
            && ClientAuthorization::allowsClient((int) $poi->client_id)
            && $user->hasPermission(Permission::POI_UPDATE);
    }

    public function delete(User $user, Poi $poi): bool
    {
        return $this->sameTenant($poi)
            && ClientAuthorization::allowsClient((int) $poi->client_id)
            && $user->hasPermission(Permission::POI_DELETE);
    }

    private function sameTenant(Poi $poi): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $poi->tenant_id);
    }
}
