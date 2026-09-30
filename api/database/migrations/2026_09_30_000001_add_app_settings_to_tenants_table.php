<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('app_name')->nullable()->after('vehicle_alert_defaults');
            $table->string('app_icon_path')->nullable()->after('app_name');
            $table->string('app_logo_path')->nullable()->after('app_icon_path');
            $table->string('app_primary_color', 9)->nullable()->after('app_logo_path');
            $table->string('app_secondary_color', 9)->nullable()->after('app_primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'app_name',
                'app_icon_path',
                'app_logo_path',
                'app_primary_color',
                'app_secondary_color',
            ]);
        });
    }
};
