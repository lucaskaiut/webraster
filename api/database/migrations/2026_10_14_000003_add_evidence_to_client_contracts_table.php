<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidências da assinatura do contrato: hashes do documento/assinatura e
 * dados da requisição que registrou a assinatura (auditoria).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_contracts', function (Blueprint $table) {
            $table->string('document_hash', 64)->nullable()->after('document_name');
            $table->string('signature_hash', 64)->nullable()->after('document_hash');
            $table->string('signed_ip', 45)->nullable()->after('signature_hash');
            $table->string('signed_user_agent', 512)->nullable()->after('signed_ip');
        });
    }

    public function down(): void
    {
        Schema::table('client_contracts', function (Blueprint $table) {
            $table->dropColumn(['document_hash', 'signature_hash', 'signed_ip', 'signed_user_agent']);
        });
    }
};
