<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('imei', 20);
            $table->string('model', 100)->nullable();
            $table->string('iccid', 30)->nullable();
            $table->string('carrier', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'imei']);
            $table->unique('vehicle_id');
            $table->index(['tenant_id', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
