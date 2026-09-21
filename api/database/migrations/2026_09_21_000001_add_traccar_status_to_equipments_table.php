<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->string('traccar_status', 20)->nullable()->after('traccar_device_id');
            $table->timestamp('traccar_last_update')->nullable()->after('traccar_status');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn(['traccar_status', 'traccar_last_update']);
        });
    }
};
