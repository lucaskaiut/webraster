<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('method', 10)->default('POST');
            $table->string('event');
            $table->json('headers')->nullable();
            $table->json('query_params')->nullable();
            $table->json('body_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('secret')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
