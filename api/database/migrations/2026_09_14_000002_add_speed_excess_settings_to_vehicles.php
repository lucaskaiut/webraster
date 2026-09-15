<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedTinyInteger('speed_hysteresis_percent')->default(3)->after('max_speed_kmh');
            $table->unsignedSmallInteger('speed_min_duration_seconds')->default(30)->after('speed_hysteresis_percent');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['speed_hysteresis_percent', 'speed_min_duration_seconds']);
        });
    }
};
