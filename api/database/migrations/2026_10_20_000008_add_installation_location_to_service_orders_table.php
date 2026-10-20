<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->text('installation_location')->nullable()->after('notes');
            $table->string('installation_location_photo_path')->nullable()->after('installation_location');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropColumn(['installation_location', 'installation_location_photo_path']);
        });
    }
};
