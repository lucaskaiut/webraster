<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('type', 40);
            $table->boolean('is_active')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->foreignId('last_gps_position_id')->nullable()->constrained('gps_positions')->nullOnDelete();
            $table->timestamp('last_recorded_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_id', 'type']);
            $table->index(['tenant_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_states');
    }
};
