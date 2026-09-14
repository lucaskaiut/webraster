<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->timestamp('server_time')->nullable()->after('recorded_at');
            $table->string('address')->nullable()->after('altitude');
            $table->boolean('valid')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->dropColumn(['server_time', 'address', 'valid']);
        });
    }
};
