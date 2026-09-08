<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_command_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->unsignedBigInteger('traccar_device_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('command_type', 80);
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('executed_at')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'equipment_id', 'created_at']);
            $table->index(['tenant_id', 'vehicle_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_command_logs');
    }
};
