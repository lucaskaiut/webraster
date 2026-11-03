<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('theme')->nullable()->after('favicon_path');
        });

        // Administradores já existentes passam a gerenciar o tema.
        Role::query()
            ->where('name', DefaultRole::ADMINISTRATOR->value)
            ->each(fn (Role $role) => $role->grantPermissions(Permission::THEME_MANAGE));
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
