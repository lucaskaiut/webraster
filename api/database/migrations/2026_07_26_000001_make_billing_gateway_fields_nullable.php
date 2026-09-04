<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('gateway', 64)->nullable()->change();
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('payment_gateway', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('payment_gateway', 64)->nullable(false)->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('gateway', 64)->nullable(false)->change();
        });
    }
};
