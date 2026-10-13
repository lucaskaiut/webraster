<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->timestamp('gateway_expires_at')->nullable()->after('gateway_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_billings', function (Blueprint $table) {
            $table->dropColumn('gateway_expires_at');
        });
    }
};
