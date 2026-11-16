<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Telefone de contato do atendimento (obrigatório no agendamento) e a
        // mensagem que o técnico envia ao cliente pelo WhatsApp no dia do serviço.
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->string('contact_phone', 20)->nullable()->after('address_reference');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->text('service_order_greeting')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropColumn('contact_phone');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('service_order_greeting');
        });
    }
};
