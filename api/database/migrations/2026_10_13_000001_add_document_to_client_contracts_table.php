<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_contracts', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('signature_path');
            $table->string('document_name')->nullable()->after('document_path');
        });

        Schema::table('client_contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('client_contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_id')->nullable(false)->change();
        });

        Schema::table('client_contracts', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_name']);
        });
    }
};
