<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alert_configs') && ! Schema::hasColumn('alert_configs', 'vehicle_id')) {
            Schema::table('alert_configs', function (Blueprint $table) {
                // FK de tenant_id usa o índice único composto; cria índice próprio antes de removê-lo.
                $table->index('tenant_id');
            });

            Schema::table('alert_configs', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'type']);

                $table->foreignId('client_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('clients')
                    ->nullOnDelete();

                $table->foreignId('vehicle_id')
                    ->nullable()
                    ->after('client_id')
                    ->constrained('vehicles')
                    ->nullOnDelete();

                $table->string('name', 150)->nullable()->after('vehicle_id');

                $table->index(['tenant_id', 'type', 'is_enabled']);
                $table->index(['tenant_id', 'client_id']);
                $table->index(['tenant_id', 'vehicle_id']);
            });
        }

        if (Schema::hasTable('alert_states') && ! Schema::hasColumn('alert_states', 'alert_config_id')) {
            Schema::table('alert_states', function (Blueprint $table) {
                $table->index('vehicle_id');
            });

            Schema::table('alert_states', function (Blueprint $table) {
                $table->dropUnique(['vehicle_id', 'type']);

                // 0 = estado compartilhado do sensor; >0 = estado por configuração.
                $table->unsignedBigInteger('alert_config_id')->default(0)->after('vehicle_id');

                $table->unique(['vehicle_id', 'type', 'alert_config_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('alert_states', 'alert_config_id')) {
            Schema::table('alert_states', function (Blueprint $table) {
                $table->dropUnique(['vehicle_id', 'type', 'alert_config_id']);
                $table->dropColumn('alert_config_id');
                $table->unique(['vehicle_id', 'type']);
            });
        }

        if (Schema::hasColumn('alert_configs', 'vehicle_id')) {
            Schema::table('alert_configs', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'type', 'is_enabled']);
                $table->dropIndex(['tenant_id', 'client_id']);
                $table->dropIndex(['tenant_id', 'vehicle_id']);
                $table->dropConstrainedForeignId('vehicle_id');
                $table->dropConstrainedForeignId('client_id');
                $table->dropColumn('name');
                $table->unique(['tenant_id', 'type']);
            });
        }
    }
};
