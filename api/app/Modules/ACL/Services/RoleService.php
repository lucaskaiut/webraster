<?php

namespace App\Modules\ACL\Services;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class RoleService
{
    /**
     * @return array<string, Role>
     */
    public function createDefaultRolesFor(Tenant $tenant): array
    {
        $roles = [];

        foreach (DefaultRole::cases() as $default) {
            $role = Role::query()->forTenant($tenant)->firstOrNew(['name' => $default->value]);
            $role->tenant_id = $tenant->getKey();
            $role->description = $default->description();
            $role->save();

            $role->syncPermissions($default->permissions());

            $roles[$default->value] = $role;
        }

        return $roles;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{name: string, description?: ?string, permissions?: list<string>}  $data
     */
    public function create(array $data): Role
    {
        $role = Role::query()->create(Arr::only($data, ['name', 'description']));

        $role->syncPermissions($this->toPermissions($data['permissions'] ?? []));

        return $role->load('permissions');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        $role->fill(Arr::only($data, ['name', 'description']));
        $role->save();

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($this->toPermissions($data['permissions'] ?? []));
        }

        return $role->refresh()->load('permissions');
    }

    public function delete(Role $role): void
    {
        $role->users()->detach();
        $role->delete();
    }

    /**
     * @param  list<string>  $values
     * @return list<Permission>
     */
    private function toPermissions(array $values): array
    {
        return array_map(
            fn (string $value) => Permission::from($value),
            array_values(array_unique($values)),
        );
    }
}
