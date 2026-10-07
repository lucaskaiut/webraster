<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_raw_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->string('imei', 32);
            $table->text('line');
            $table->timestamp('device_time')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['equipment_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_raw_logs');
    }
};
