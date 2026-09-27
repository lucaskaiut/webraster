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

        Role::query()
            ->where('name', DefaultRole::OPERATOR->value)
            ->each(function (Role $role): void {
                $grant = [
                    Permission::CRM_PIPELINE_VIEW,
                    Permission::CRM_LEAD_VIEW,
                    Permission::CRM_LEAD_UPDATE,
                    Permission::CRM_CONVERSATION_VIEW,
                    Permission::CRM_CONVERSATION_REPLY,
                    Permission::CRM_AI_VIEW,
                ];

                $existing = $role->permissionValues()->toArray();
                $toAdd = array_filter($grant, fn (Permission $p) => ! in_array($p->value, $existing, true));

                if ($toAdd !== []) {
                    $role->grantPermissions(...$toAdd);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
