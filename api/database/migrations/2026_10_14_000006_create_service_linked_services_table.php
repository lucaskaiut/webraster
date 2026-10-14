<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serviços vinculados: ao contratar o serviço no pedido do cliente, os serviços
 * vinculados são sugeridos na ordem de serviço (ex.: mensalidade → instalação).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_linked_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('linked_service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'linked_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_linked_services');
    }
};
