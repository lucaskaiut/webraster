<?php

namespace App\Modules\ApiToken\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class ApiTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::API_TOKEN_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::API_TOKEN_CREATE);
    }

    public function delete(User $user, ApiToken $apiToken): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $apiToken->tenant_id)
            && $user->hasPermission(Permission::API_TOKEN_DELETE);
    }
}
