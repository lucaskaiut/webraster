<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofence_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('geofence_id')->constrained('geofences')->cascadeOnDelete();
            $table->foreignId('gps_position_id')->nullable()->constrained('gps_positions')->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at');
            $table->timestamp('processed_at');
            $table->decimal('speed', 8, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['gps_position_id', 'geofence_id', 'type'], 'geofence_events_idempotent');
            $table->index(['tenant_id', 'client_id', 'recorded_at']);
            $table->index(['tenant_id', 'vehicle_id', 'recorded_at']);
            $table->index(['tenant_id', 'geofence_id', 'recorded_at']);
            $table->index(['tenant_id', 'type', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_events');
    }
};
