<?php

namespace App\Modules\ACL\Models\Concerns;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
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
     * @return Collection<int, string>
     */
    public function permissionValues(): Collection
    {
        return $this->loadedRoles()
            ->flatMap(fn (Role $role) => $role->permissionValues())
            ->unique()
            ->values();
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
