<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posições de equipamentos ainda sem vínculo com veículo (estoque) passam a
 * ser armazenadas para exibir os indicadores no cadastro/lista de equipamentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->change();
            $table->foreignId('client_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable(false)->change();
            $table->foreignId('client_id')->nullable(false)->change();
        });
    }
};
