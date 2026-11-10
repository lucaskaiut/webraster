<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Régua de cobrança: avisos ao cliente antes, no dia e depois do vencimento,
 * com texto configurável por empresa, e bloqueio do acesso do cliente
 * inadimplente (só o financeiro fica liberado). Também concede as novas
 * permissões aos papéis existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_collection_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->boolean('before_due_enabled')->default(true);
            $table->unsignedSmallInteger('before_due_days')->default(3);
            $table->text('before_due_message')->nullable();
            $table->boolean('due_date_enabled')->default(true);
            $table->text('due_date_message')->nullable();
            $table->boolean('overdue_enabled')->default(true);
            $table->unsignedSmallInteger('overdue_interval_days')->default(3);
            $table->unsignedSmallInteger('overdue_max_reminders')->nullable();
            $table->text('overdue_message')->nullable();
            $table->boolean('block_access_enabled')->default(false);
            $table->unsignedSmallInteger('block_access_after_days')->default(5);
            $table->timestamps();
        });

        $grants = [
            DefaultRole::ADMINISTRATOR->value => [
                Permission::FINANCE_COLLECTION_CONFIG_READ,
                Permission::FINANCE_COLLECTION_CONFIG_UPDATE,
            ],
            DefaultRole::OPERATOR->value => [
                Permission::FINANCE_COLLECTION_CONFIG_READ,
                Permission::FINANCE_COLLECTION_CONFIG_UPDATE,
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
        Schema::dropIfExists('finance_collection_settings');
    }
};
