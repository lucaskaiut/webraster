<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Exceptions\TenantAccessForbidden;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class MasterTenantAccessService
{
    /**
     * Tenant home (parent) + filhos que o usuário master pode operar.
     *
     * @return Collection<int, Tenant>
     */
    public function availableTenants(User $user): Collection
    {
        if (! $user->is_master) {
            return new Collection;
        }

        $home = $user->tenant()->first(['id', 'uuid', 'name', 'parent_id']);

        if ($home === null) {
            return new Collection;
        }

        $home->setAttribute('is_home', true);

        $children = Tenant::query()
            ->where('parent_id', $user->tenant_id)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'parent_id'])
            ->each(fn (Tenant $tenant) => $tenant->setAttribute('is_home', false));

        return (new Collection([$home]))->concat($children)->values();
    }

    public function canAccess(User $user, Tenant|int $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        if ($user->tenant_id === $tenantId) {
            return true;
        }

        if (! $user->is_master) {
            return false;
        }

        return Tenant::query()
            ->where('parent_id', $user->tenant_id)
            ->whereKey($tenantId)
            ->exists();
    }

    /**
     * Resolve o tenant pelo identificador público (UUID) ou ID interno.
     *
     * @throws TenantAccessForbidden
     */
    public function resolveAccessibleTenant(User $user, string $identifier): Tenant
    {
        $tenant = $this->findTenant($identifier);

        if ($tenant === null || ! $this->canAccess($user, $tenant)) {
            throw TenantAccessForbidden::make();
        }

        return $tenant;
    }

    private function findTenant(string $identifier): ?Tenant
    {
        if (Str::isUuid($identifier)) {
            return Tenant::query()->where('uuid', $identifier)->first();
        }

        if (ctype_digit($identifier)) {
            return Tenant::query()->find((int) $identifier);
        }

        return null;
    }
}
