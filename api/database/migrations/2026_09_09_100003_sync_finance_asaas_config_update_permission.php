<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $operatorPermissions = [
            Permission::FINANCE_ASAAS_CONFIG_UPDATE,
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
