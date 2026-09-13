<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('transmission', 20)->nullable()->after('year');
            $table->unsignedBigInteger('odometer')->nullable()->after('transmission');
            $table->decimal('average_consumption', 5, 2)->nullable()->after('odometer');
            $table->decimal('tank_capacity', 5, 2)->nullable()->after('average_consumption');
            $table->string('crlv_file', 255)->nullable()->after('tank_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'transmission',
                'odometer',
                'average_consumption',
                'tank_capacity',
                'crlv_file',
            ]);
        });
    }
};
