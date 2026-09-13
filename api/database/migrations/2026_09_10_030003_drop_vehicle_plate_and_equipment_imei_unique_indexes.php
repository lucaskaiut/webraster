<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'plate']);
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'imei']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique(['tenant_id', 'plate']);
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->unique(['tenant_id', 'imei']);
        });
    }
};
