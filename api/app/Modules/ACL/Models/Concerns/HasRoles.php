<?php

namespace App\Modules\ACL\Models\Concerns;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        // Roles pertencem ao tenant "home" do usuário. Masters operando
        // filhos não devem perder permissões pelo TenantScope ativo.
        return $this->belongsToMany(Role::class, 'user_roles')->withoutTenancy();
    }

    public function assignRole(Role $role): void
    {
        $this->roles()->syncWithoutDetaching([$role->getKey()]);
        $this->unsetRelation('roles');
    }

    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->getKey());
        $this->unsetRelation('roles');
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync(array_values(array_unique(array_map('intval', $roleIds))));
        $this->unsetRelation('roles');
    }

    public function hasRole(Role|string $role): bool
    {
        $name = $role instanceof Role ? $role->name : $role;

        return $this->loadedRoles()->contains(
            fn (Role $assigned) => $assigned->name === $name,
        );
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $permission = $permission instanceof Permission
            ? $permission
            : Permission::tryFrom($permission);

        if ($permission === null) {
            return false;
        }

        return $this->permissionValues()->contains($permission->value);
    }

    /**
     * Permissões dos perfis, restritas aos módulos liberados para o tenant
     * em que o usuário opera. Master operando uma empresa filha tem todas
     * as permissões liberadas para ela: os perfis do umbrella só tratam da
     * administração do grupo.
     *
     * @return Collection<int, string>
     */
    public function permissionValues(): Collection
    {
        $tenant = $this->permissionTenant();

        if ($this->is_master && $tenant !== null && ! $tenant->isUmbrella()) {
            return collect(Permission::values())
                ->filter(fn (string $permission): bool => $tenant->allowsPermission($permission))
                ->values();
        }

        return $this->loadedRoles()
            ->flatMap(fn (Role $role) => $role->permissionValues())
            ->unique()
            ->filter(fn (string $permission): bool => $tenant === null || $tenant->allowsPermission($permission))
            ->values();
    }

    /**
     * Usuários comuns só operam o próprio tenant. Masters podem operar um
     * filho (X-Tenant-Id), então valem os módulos do tenant em contexto.
     */
    protected function permissionTenant(): ?Tenant
    {
        if ($this->is_master && TenantContext::isResolved()) {
            return TenantContext::tenant();
        }

        return $this->tenant;
    }

    /**
     * @return Collection<int, Role>
     */
    protected function loadedRoles(): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles->toBase();
    }
}
