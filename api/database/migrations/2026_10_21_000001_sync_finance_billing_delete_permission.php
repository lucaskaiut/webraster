<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Concede a permissão de exclusão de cobranças aos papéis existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grants = [
            DefaultRole::ADMINISTRATOR->value => [
                Permission::FINANCE_BILLING_DELETE,
            ],
            DefaultRole::OPERATOR->value => [
                Permission::FINANCE_BILLING_DELETE,
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
