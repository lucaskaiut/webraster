<?php

namespace App\Modules\Alert\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Alert\Models\Alert;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ALERT_READ);
    }

    public function view(User $user, Alert $alert): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $alert->tenant_id)
            && ($alert->client_id === null || ClientAuthorization::allowsClient((int) $alert->client_id))
            && $user->hasPermission(Permission::ALERT_READ);
    }

    public function manage(User $user, Alert $alert): bool
    {
        return $this->view($user, $alert)
            && $user->hasPermission(Permission::ALERT_MANAGE);
    }
}
