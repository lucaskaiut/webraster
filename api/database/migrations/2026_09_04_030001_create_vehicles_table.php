<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('plate', 10);
            $table->string('chassis', 30)->nullable();
            $table->string('renavam', 20)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'plate']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'brand', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
