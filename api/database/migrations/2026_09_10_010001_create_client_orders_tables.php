<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('finance_subscription_id')->nullable()->constrained('finance_subscriptions')->nullOnDelete();
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->string('periodicity', 32)->default('monthly');
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique('client_id');
            $table->index(['tenant_id', 'client_id']);
        });

        Schema::create('client_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_order_id')->constrained('client_orders')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('service_name');
            $table->unsignedBigInteger('unit_amount_cents');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_cents');
            $table->timestamps();

            $table->unique(['client_order_id', 'service_id']);
        });

        Schema::create('client_order_item_vehicle', function (Blueprint $table) {
            $table->foreignId('client_order_item_id')->constrained('client_order_items')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();

            $table->primary(['client_order_item_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_order_item_vehicle');
        Schema::dropIfExists('client_order_items');
        Schema::dropIfExists('client_orders');
    }
};
