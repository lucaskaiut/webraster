<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajuste pontual da jornada do técnico em um dia: antecipar o início e/ou
 * estender o fim, sem alterar a jornada semanal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_work_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            // Novo início do primeiro intervalo do dia (antecipação).
            $table->time('starts_at')->nullable();
            // Novo fim do último intervalo do dia (extensão).
            $table->time('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_work_day_overrides');
    }
};
