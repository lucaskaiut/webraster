<?php

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Builder;

return new class extends Migration
{
    public function up(): void
    {
        Role::query()
            ->whereHas(
                'permissions',
                fn (Builder $query) => $query->where('permission', Permission::EQUIPMENT_READ->value),
            )
            ->each(function (Role $role): void {
                if (! $role->hasPermission(Permission::EQUIPMENT_DETAILS_READ)) {
                    $role->grantPermissions(Permission::EQUIPMENT_DETAILS_READ);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
