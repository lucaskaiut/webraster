<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_geofence_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('geofence_id')->constrained('geofences')->cascadeOnDelete();
            $table->boolean('is_inside')->default(false);
            $table->foreignId('last_gps_position_id')->nullable()->constrained('gps_positions')->nullOnDelete();
            $table->timestamp('last_recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_id', 'geofence_id']);
            $table->index(['tenant_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_geofence_states');
    }
};
