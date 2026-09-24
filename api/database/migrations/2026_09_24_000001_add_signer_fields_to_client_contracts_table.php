<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_contracts', function (Blueprint $table) {
            $table->string('signer_name')->nullable()->after('signature_path');
            $table->string('signer_cpf', 11)->nullable()->after('signer_name');
            $table->date('signer_birth_date')->nullable()->after('signer_cpf');
        });
    }

    public function down(): void
    {
        Schema::table('client_contracts', function (Blueprint $table) {
            $table->dropColumn(['signer_name', 'signer_cpf', 'signer_birth_date']);
        });
    }
};
