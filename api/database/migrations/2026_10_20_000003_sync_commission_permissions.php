<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Concede as permissões do módulo de comissões aos papéis existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grants = [
            DefaultRole::ADMINISTRATOR->value => [
                Permission::COMMISSION_READ,
                Permission::COMMISSION_VIEW_OTHERS,
            ],
            DefaultRole::OPERATOR->value => [
                Permission::COMMISSION_READ,
                Permission::COMMISSION_VIEW_OTHERS,
            ],
            DefaultRole::TECHNICIAN->value => [
                Permission::COMMISSION_READ,
            ],
        ];

        foreach ($grants as $roleName => $permissions) {
            Role::query()
                ->where('name', $roleName)
                ->each(function (Role $role) use ($permissions): void {
                    $existing = $role->permissionValues()->toArray();
                    $missing = array_filter(
                        $permissions,
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
