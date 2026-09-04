<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Tenant::query()->get() as $tenant) {
            $roles = app(RoleService::class)->createDefaultRolesFor($tenant);

            $legacy = Role::query()
                ->forTenant($tenant)
                ->where('name', 'Usuário')
                ->first();

            if ($legacy === null) {
                continue;
            }

            $operatorId = $roles[DefaultRole::OPERATOR->value]->getKey();
            $legacyId = $legacy->getKey();

            $operatorUserIds = DB::table('user_roles')
                ->where('role_id', $operatorId)
                ->pluck('user_id')
                ->all();

            $query = DB::table('user_roles')->where('role_id', $legacyId);

            if ($operatorUserIds !== []) {
                $query->whereNotIn('user_id', $operatorUserIds);
            }

            $query->update(['role_id' => $operatorId]);

            DB::table('user_roles')->where('role_id', $legacyId)->delete();
            DB::table('role_permissions')->where('role_id', $legacyId)->delete();
            $legacy->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
