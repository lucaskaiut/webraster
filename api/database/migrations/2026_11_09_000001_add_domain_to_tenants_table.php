<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('domain')->nullable()->unique()->after('identifier');
            $table->string('domain_status', 20)->nullable()->after('domain');
            $table->text('domain_error')->nullable()->after('domain_status');
            $table->timestamp('domain_provisioned_at')->nullable()->after('domain_error');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->dropColumn(['domain', 'domain_status', 'domain_error', 'domain_provisioned_at']);
        });
    }
};
