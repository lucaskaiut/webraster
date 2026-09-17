<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'gps_positions_vehicle_recorded_idx';

    public function up(): void
    {
        if (Schema::hasIndex('gps_positions', self::INDEX)) {
            return;
        }

        Schema::table('gps_positions', function (Blueprint $table) {
            $table->index(['vehicle_id', 'recorded_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('gps_positions', self::INDEX)) {
            return;
        }

        Schema::table('gps_positions', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }
};
