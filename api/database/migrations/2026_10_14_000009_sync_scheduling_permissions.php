<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Cria o papel Técnico nos tenants existentes e concede as novas permissões
 * de agendamento a Administrador e Operador, sem mexer nas demais permissões.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Tenant::query()->get() as $tenant) {
            $role = Role::query()->forTenant($tenant)->firstOrNew(['name' => DefaultRole::TECHNICIAN->value]);

            if (! $role->exists) {
                $role->tenant_id = $tenant->getKey();
                $role->description = DefaultRole::TECHNICIAN->description();
                $role->save();
                $role->syncPermissions(DefaultRole::TECHNICIAN->permissions());
            }
        }

        $grants = [
            DefaultRole::ADMINISTRATOR->value => [
                Permission::SERVICE_ORDER_EXECUTE,
                Permission::TECHNICIAN_READ,
                Permission::TECHNICIAN_MANAGE,
            ],
            DefaultRole::OPERATOR->value => [
                Permission::TECHNICIAN_READ,
                Permission::TECHNICIAN_MANAGE,
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
