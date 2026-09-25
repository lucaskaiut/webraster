<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_configs', function (Blueprint $table) {
            $table->boolean('notify_push')->default(true)->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('alert_configs', function (Blueprint $table) {
            $table->dropColumn('notify_push');
        });
    }
};
