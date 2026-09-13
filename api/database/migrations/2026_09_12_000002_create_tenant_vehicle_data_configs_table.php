<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_vehicle_data_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->boolean('is_active')->default(false);
            $table->text('credentials');
            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);
        });

        foreach (Tenant::query()->get() as $tenant) {
            app(RoleService::class)->createDefaultRolesFor($tenant);
        }

        $permissions = [
            Permission::VEHICLE_DATA_CONFIG_READ,
            Permission::VEHICLE_DATA_CONFIG_UPDATE,
        ];

        Role::query()
            ->whereIn('name', [DefaultRole::ADMINISTRATOR->value, DefaultRole::OPERATOR->value])
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

    public function down(): void
    {
        Schema::dropIfExists('tenant_vehicle_data_configs');
    }
};
