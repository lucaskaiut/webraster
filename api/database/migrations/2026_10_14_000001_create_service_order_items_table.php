<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_cents')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'service_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_items');
    }
};
