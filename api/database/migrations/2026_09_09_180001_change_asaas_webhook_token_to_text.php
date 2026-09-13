<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_asaas_configs', function (Blueprint $table) {
            $table->text('webhook_token')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_asaas_configs', function (Blueprint $table) {
            $table->string('webhook_token')->change();
        });
    }
};
