<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = Permission::values();

        Role::query()
            ->where('name', DefaultRole::ADMINISTRATOR->value)
            ->each(function (Role $role) use ($permissions) {
                $existing = $role->permissionValues()->toArray();
                $newPermissions = array_diff($permissions, $existing);

                if (! empty($newPermissions)) {
                    $role->grantPermissions(
                        ...array_map(fn (string $p) => Permission::from($p), $newPermissions),
                    );
                }
            });
    }

    public function down(): void
    {
        //
    }
};
