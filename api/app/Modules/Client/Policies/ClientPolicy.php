<?php

namespace App\Modules\Client\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CLIENT_READ);
    }

    public function view(User $user, Client $client): bool
    {
        return $this->sameTenant($client)
            && ClientAuthorization::allowsClient((int) $client->getKey())
            && $user->hasPermission(Permission::CLIENT_READ);
    }

    public function create(User $user): bool
    {
        if (ClientAuthorization::isRestricted()) {
            return false;
        }

        return $user->hasPermission(Permission::CLIENT_CREATE);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->sameTenant($client)
            && ClientAuthorization::allowsClient((int) $client->getKey())
            && $user->hasPermission(Permission::CLIENT_UPDATE);
    }

    public function signContract(User $user, Client $client): bool
    {
        return $this->sameTenant($client)
            && ClientAuthorization::allowsClient((int) $client->getKey())
            && $user->hasPermission(Permission::CONTRACT_SIGN);
    }

    public function delete(User $user, Client $client): bool
    {
        if (ClientAuthorization::isRestricted()) {
            return false;
        }

        return $this->sameTenant($client) && $user->hasPermission(Permission::CLIENT_DELETE);
    }

    public function manageUsers(User $user, Client $client): bool
    {
        return $this->sameTenant($client)
            && ClientAuthorization::allowsClient((int) $client->getKey())
            && ($user->hasPermission(Permission::CLIENT_UPDATE) || $user->hasPermission(Permission::USER_CREATE));
    }

    private function sameTenant(Client $client): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $client->tenant_id);
    }
}
