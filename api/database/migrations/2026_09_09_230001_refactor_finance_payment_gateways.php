<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('finance_asaas_customers');
        Schema::dropIfExists('tenant_asaas_configs');
        Schema::dropIfExists('finance_gateway_customers');
        Schema::dropIfExists('tenant_payment_gateway_configs');

        Schema::create('tenant_payment_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('gateway', 64);
            $table->boolean('is_active')->default(true);
            $table->text('credentials');
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway']);
        });

        Schema::create('finance_gateway_customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('gateway', 64);
            $table->string('external_customer_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'client_id', 'gateway']);
            $table->index(['tenant_id', 'gateway'], 'finance_gateway_customers_tenant_gateway_index');
        });

        Schema::table('finance_billings', function (Blueprint $table) {
            $table->string('payment_gateway', 64)->nullable()->after('payment_method');
        });

        DB::table('role_permissions')
            ->whereIn('permission', [
                'finance-asaas-config.read',
                'finance-asaas-config.update',
            ])
            ->delete();

        foreach (Tenant::query()->get() as $tenant) {
            app(RoleService::class)->createDefaultRolesFor($tenant);
        }

        $operatorPermissions = [
            Permission::FINANCE_GATEWAY_CONFIG_READ,
            Permission::FINANCE_GATEWAY_CONFIG_UPDATE,
        ];

        Role::query()
            ->whereIn('name', [DefaultRole::ADMINISTRATOR->value, DefaultRole::OPERATOR->value])
            ->each(function (Role $role) use ($operatorPermissions): void {
                $existing = $role->permissionValues()->toArray();
                $missing = array_filter(
                    $operatorPermissions,
                    fn (Permission $permission) => ! in_array($permission->value, $existing, true),
                );

                if ($missing !== []) {
                    $role->grantPermissions(...array_values($missing));
                }
            });
    }

    public function down(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->dropColumn('payment_gateway');
        });
        Schema::dropIfExists('finance_gateway_customers');
        Schema::dropIfExists('tenant_payment_gateway_configs');
    }
};
