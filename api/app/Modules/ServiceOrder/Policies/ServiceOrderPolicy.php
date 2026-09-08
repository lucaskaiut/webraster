<?php

namespace App\Modules\ServiceOrder\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class ServiceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SERVICE_ORDER_READ);
    }

    public function view(User $user, ServiceOrder $order): bool
    {
        return $this->sameTenant($order)
            && ClientAuthorization::allowsClient((int) $order->client_id)
            && $user->hasPermission(Permission::SERVICE_ORDER_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SERVICE_ORDER_CREATE);
    }

    public function update(User $user, ServiceOrder $order): bool
    {
        return $this->sameTenant($order)
            && ClientAuthorization::allowsClient((int) $order->client_id)
            && $user->hasPermission(Permission::SERVICE_ORDER_UPDATE);
    }

    public function delete(User $user, ServiceOrder $order): bool
    {
        return $this->sameTenant($order)
            && ClientAuthorization::allowsClient((int) $order->client_id)
            && $user->hasPermission(Permission::SERVICE_ORDER_DELETE);
    }

    public function changeStatus(User $user, ServiceOrder $order): bool
    {
        return $this->sameTenant($order)
            && ClientAuthorization::allowsClient((int) $order->client_id)
            && $user->hasPermission(Permission::SERVICE_ORDER_CHANGE_STATUS);
    }

    private function sameTenant(ServiceOrder $order): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $order->tenant_id);
    }
}
