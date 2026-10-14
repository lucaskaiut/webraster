<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O campo antes chamado ICCID passa a armazenar o número da linha
 * (MSISDN) do chip instalado no equipamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->renameColumn('iccid', 'line_number');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->renameColumn('line_number', 'iccid');
        });
    }
};
