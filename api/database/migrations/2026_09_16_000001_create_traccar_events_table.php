<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traccar_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('traccar_device_id')->nullable();
            $table->string('dedupe_key', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('payload');
            $table->text('last_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('traccar_device_id');
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traccar_events');
    }
};
