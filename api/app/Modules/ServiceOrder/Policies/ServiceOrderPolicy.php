<?php

namespace App\Modules\ServiceOrder\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Support\ClientAuthorization;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
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
        return $this->reachable($user, $order)
            && $user->hasPermission(Permission::SERVICE_ORDER_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SERVICE_ORDER_CREATE);
    }

    public function update(User $user, ServiceOrder $order): bool
    {
        return $this->reachable($user, $order)
            && $user->hasPermission(Permission::SERVICE_ORDER_UPDATE);
    }

    public function delete(User $user, ServiceOrder $order): bool
    {
        return $this->reachable($user, $order)
            && $user->hasPermission(Permission::SERVICE_ORDER_DELETE);
    }

    /**
     * Cancelar exige service-order.cancel; as demais transições
     * (iniciar/concluir) exigem service-order.change-status.
     */
    public function changeStatus(User $user, ServiceOrder $order, ?ServiceOrderStatus $next = null): bool
    {
        $permission = $next === ServiceOrderStatus::CANCELLED
            ? Permission::SERVICE_ORDER_CANCEL
            : Permission::SERVICE_ORDER_CHANGE_STATUS;

        return $this->reachable($user, $order)
            && $user->hasPermission($permission);
    }

    public function generateBilling(User $user, ServiceOrder $order): bool
    {
        return $this->view($user, $order)
            && $user->hasPermission(Permission::FINANCE_BILLING_CREATE);
    }

    /**
     * Mesmo tenant, mesmo cliente (portal) e visível ao usuário
     * (próprias OS, salvo service-order.view-all).
     */
    private function reachable(User $user, ServiceOrder $order): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $order->tenant_id)
            && ClientAuthorization::allowsClient((int) $order->client_id)
            && $order->isVisibleTo($user);
    }
}
