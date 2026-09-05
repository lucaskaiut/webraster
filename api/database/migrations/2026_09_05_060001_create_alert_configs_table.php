<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 40);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_email')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_configs');
    }
};
