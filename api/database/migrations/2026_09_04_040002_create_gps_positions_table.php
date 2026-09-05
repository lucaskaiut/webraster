<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at');
            $table->decimal('speed', 8, 2)->nullable();
            $table->boolean('ignition')->nullable();
            $table->decimal('battery', 5, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->unsignedBigInteger('traccar_position_id')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'traccar_position_id']);
            $table->index(['tenant_id', 'vehicle_id', 'recorded_at'], 'gps_pos_vehicle_recorded_idx');
            $table->index(['tenant_id', 'client_id'], 'gps_pos_client_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_positions');
    }
};
