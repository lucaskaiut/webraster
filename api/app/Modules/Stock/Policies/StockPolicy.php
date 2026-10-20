<?php

namespace App\Modules\Stock\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Stock\Models\Stock;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class StockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::STOCK_READ);
    }

    public function view(User $user, Stock $stock): bool
    {
        return $this->sameTenant($stock) && $user->hasPermission(Permission::STOCK_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::STOCK_CREATE);
    }

    public function update(User $user, Stock $stock): bool
    {
        return $this->sameTenant($stock) && $user->hasPermission(Permission::STOCK_UPDATE);
    }

    public function delete(User $user, Stock $stock): bool
    {
        return $this->sameTenant($stock) && $user->hasPermission(Permission::STOCK_DELETE);
    }

    private function sameTenant(Stock $stock): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $stock->tenant_id);
    }
}
