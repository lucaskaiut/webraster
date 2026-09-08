<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Tenant::query()->get() as $tenant) {
            app(RoleService::class)->createDefaultRolesFor($tenant);
        }

        $permissions = Permission::values();

        Role::query()
            ->where('name', DefaultRole::ADMINISTRATOR->value)
            ->each(function (Role $role) use ($permissions): void {
                $existing = $role->permissionValues()->toArray();
                $newPermissions = array_diff($permissions, $existing);

                if ($newPermissions !== []) {
                    $role->grantPermissions(
                        ...array_map(fn (string $permission) => Permission::from($permission), $newPermissions),
                    );
                }
            });

        $operatorPermissions = [
            Permission::DEVICE_COMMANDS_SEND,
        ];

        Role::query()
            ->where('name', DefaultRole::OPERATOR->value)
            ->each(function (Role $role) use ($operatorPermissions): void {
                $existing = $role->permissionValues()->toArray();
                $missing = array_filter(
                    $operatorPermissions,
                    fn (Permission $permission) => ! in_array($permission->value, $existing, true),
                );

                if ($missing !== []) {
                    $role->grantPermissions(...array_values($missing));
                }
            });
    }

    public function down(): void
    {
        //
    }
};
