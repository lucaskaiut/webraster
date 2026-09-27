<?php

namespace App\Modules\Chat\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class MessagingConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CRM_GATEWAY_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CRM_GATEWAY_MANAGE);
    }

    public function update(User $user, MessagingConnection $connection): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $connection->tenant_id)
            && $user->hasPermission(Permission::CRM_GATEWAY_MANAGE);
    }
}
