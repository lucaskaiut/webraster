<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->change();
            $table->foreignId('service_order_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained('service_orders')
                ->nullOnDelete();

            $table->index(['tenant_id', 'service_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'service_order_id']);
            $table->dropConstrainedForeignId('service_order_id');
            $table->foreignId('subscription_id')->nullable(false)->change();
        });
    }
};
