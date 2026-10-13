<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('late_fine_percent', 5, 2)->nullable()->after('signature_path');
            $table->decimal('late_interest_percent', 5, 2)->nullable()->after('late_fine_percent');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['late_fine_percent', 'late_interest_percent']);
        });
    }
};
