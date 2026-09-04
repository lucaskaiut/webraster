<?php

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->permissions();

        $roles = Role::query()->with('tenant')->get();

        foreach ($roles as $role) {
            $role->grantPermissions(...$permissions);
        }
    }

    public function down(): void
    {
        $permissions = $this->permissions();

        $roles = Role::query()->with('tenant')->get();

        foreach ($roles as $role) {
            $role->revokePermissions(...$permissions);
        }
    }

    /**
     * @return list<Permission>
     */
    private function permissions(): array
    {
        return [
            Permission::PLAN_CREATE,
            Permission::PLAN_READ,
            Permission::PLAN_UPDATE,
            Permission::PLAN_DELETE,
            Permission::SUBSCRIPTION_READ,
            Permission::SUBSCRIPTION_UPDATE,
            Permission::INVOICE_READ,
        ];
    }
};
