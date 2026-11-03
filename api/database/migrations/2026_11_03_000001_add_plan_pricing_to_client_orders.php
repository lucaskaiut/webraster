<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_plans', function (Blueprint $table) {
            $table->string('pricing_type', 20)->default('fixed')->after('amount_cents');
        });

        // O item do pedido passa a referenciar um plano; itens antigos
        // (baseados em serviço) continuam válidos para histórico e contrato.
        // Índice próprio para a FK antes de remover o unique composto (MySQL).
        Schema::table('client_order_items', function (Blueprint $table) {
            $table->index('client_order_id');
        });

        Schema::table('client_order_items', function (Blueprint $table) {
            $table->dropUnique(['client_order_id', 'service_id']);
        });

        Schema::table('client_order_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->change();
            $table->foreignId('plan_id')
                ->nullable()
                ->after('service_id')
                ->constrained('finance_plans')
                ->nullOnDelete();
            $table->string('pricing_type', 20)->nullable()->after('service_name');
        });
    }

    public function down(): void
    {
        Schema::table('client_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('pricing_type');
        });

        Schema::table('client_order_items', function (Blueprint $table) {
            $table->unique(['client_order_id', 'service_id']);
            $table->dropIndex(['client_order_id']);
        });

        Schema::table('finance_plans', function (Blueprint $table) {
            $table->dropColumn('pricing_type');
        });
    }
};
