<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal `notify_monitoring`: notificação no app para os operadores do tenant
 * (o In-app passa a notificar apenas os usuários do cliente). Fica como
 * migration separada porque a `220001` já havia rodado em produção sem ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('alert_configs', 'notify_monitoring')) {
            return;
        }

        Schema::table('alert_configs', function (Blueprint $table): void {
            $table->boolean('notify_monitoring')->default(true)->after('notify_in_app');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('alert_configs', 'notify_monitoring')) {
            return;
        }

        Schema::table('alert_configs', function (Blueprint $table): void {
            $table->dropColumn('notify_monitoring');
        });
    }
};
