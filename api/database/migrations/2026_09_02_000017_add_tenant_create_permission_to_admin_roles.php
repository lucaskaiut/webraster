<?php

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
    }

    public function down(): void
    {
        //
    }
};
