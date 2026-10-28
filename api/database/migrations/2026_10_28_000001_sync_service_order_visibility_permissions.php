<?php

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Preserva o comportamento atual dos perfis existentes:
 * - quem visualiza OS continua vendo todas (service-order.view-all);
 * - quem altera status continua podendo cancelar (service-order.cancel).
 */
return new class extends Migration
{
    public function up(): void
    {
        $grants = [
            Permission::SERVICE_ORDER_READ->value => Permission::SERVICE_ORDER_VIEW_ALL,
            Permission::SERVICE_ORDER_CHANGE_STATUS->value => Permission::SERVICE_ORDER_CANCEL,
        ];

        foreach ($grants as $required => $granted) {
            Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('permission', $required))
                ->each(fn (Role $role) => $role->grantPermissions($granted));
        }
    }

    public function down(): void
    {
        //
    }
};
