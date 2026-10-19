<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referências da sequência de atendimentos do dia. Os campos de tempo e
 * distância já existentes passam a representar os trechos reais:
 * origem anterior → esta OS e esta OS → próxima origem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('travel_from_order_id')
                ->nullable()
                ->after('technician_id')
                ->constrained('service_orders')
                ->nullOnDelete();

            $table->foreignId('travel_to_order_id')
                ->nullable()
                ->after('travel_from_order_id')
                ->constrained('service_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('travel_to_order_id');
            $table->dropConstrainedForeignId('travel_from_order_id');
        });
    }
};
