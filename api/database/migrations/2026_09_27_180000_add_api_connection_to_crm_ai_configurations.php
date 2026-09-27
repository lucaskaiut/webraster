<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_ai_configurations', function (Blueprint $table): void {
            $table->string('api_endpoint')->nullable()->after('enabled');
            $table->text('api_key')->nullable()->after('api_endpoint');
        });
    }

    public function down(): void
    {
        Schema::table('crm_ai_configurations', function (Blueprint $table): void {
            $table->dropColumn(['api_endpoint', 'api_key']);
        });
    }
};
