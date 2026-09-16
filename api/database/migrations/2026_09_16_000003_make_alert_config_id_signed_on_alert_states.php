<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alarmes de dispositivo usam escopos negativos (hash de crc32) para não
     * colidir com IDs de alert_configs. A coluna precisa aceitar negativos.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('alert_states', 'alert_config_id')) {
            return;
        }

        Schema::table('alert_states', function (Blueprint $table) {
            $table->bigInteger('alert_config_id')->default(0)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('alert_states', 'alert_config_id')) {
            return;
        }

        Schema::table('alert_states', function (Blueprint $table) {
            $table->unsignedBigInteger('alert_config_id')->default(0)->change();
        });
    }
};
