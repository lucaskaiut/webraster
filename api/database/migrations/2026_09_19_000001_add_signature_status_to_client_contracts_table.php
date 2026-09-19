<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_contracts', function (Blueprint $table) {
            $table->string('signature_status', 32)->default('pending')->after('valid_until');
            $table->timestamp('signed_at')->nullable()->after('signature_status');

            $table->index(['tenant_id', 'signature_status']);
        });
    }

    public function down(): void
    {
        Schema::table('client_contracts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'signature_status']);
            $table->dropColumn(['signature_status', 'signed_at']);
        });
    }
};
