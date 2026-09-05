<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_assignment_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('previous_equipment_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->string('event', 20);
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'occurred_at'], 'eq_assign_vehicle_occurred_idx');
            $table->index(['tenant_id', 'equipment_id', 'occurred_at'], 'eq_assign_equipment_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_assignment_events');
    }
};
