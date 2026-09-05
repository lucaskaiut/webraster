<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->foreignId('gps_position_id')->nullable()->constrained('gps_positions')->nullOnDelete();
            $table->string('type', 40);
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'occurred_at']);
            $table->index(['tenant_id', 'type', 'occurred_at']);
            $table->index(['tenant_id', 'severity', 'status']);
            $table->index(['tenant_id', 'vehicle_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
