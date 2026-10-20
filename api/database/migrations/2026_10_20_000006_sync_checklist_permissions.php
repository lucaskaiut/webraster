<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Concede as permissões de gestão de checklists aos papéis existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            Permission::CHECKLIST_CREATE,
            Permission::CHECKLIST_READ,
            Permission::CHECKLIST_UPDATE,
            Permission::CHECKLIST_DELETE,
        ];

        $grants = [
            DefaultRole::ADMINISTRATOR->value => $permissions,
            DefaultRole::OPERATOR->value => $permissions,
        ];

        foreach ($grants as $roleName => $rolePermissions) {
            Role::query()
                ->where('name', $roleName)
                ->each(function (Role $role) use ($rolePermissions): void {
                    $existing = $role->permissionValues()->toArray();
                    $missing = array_filter(
                        $rolePermissions,
                        fn (Permission $permission) => ! in_array($permission->value, $existing, true),
                    );

                    if ($missing !== []) {
                        $role->grantPermissions(...array_values($missing));
                    }
                });
        }
    }

    public function down(): void
    {
        //
    }
};
