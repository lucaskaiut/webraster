<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('fipe_code', 20)->nullable()->after('crlv_file');
            $table->string('fipe_model_year', 10)->nullable()->after('fipe_code');
            $table->string('fipe_fuel', 50)->nullable()->after('fipe_model_year');
            $table->string('fipe_reference_month', 50)->nullable()->after('fipe_fuel');
            $table->string('fipe_value', 50)->nullable()->after('fipe_reference_month');
            $table->string('fipe_model', 255)->nullable()->after('fipe_value');
            $table->string('fipe_brand', 100)->nullable()->after('fipe_model');
            $table->unsignedSmallInteger('fipe_score')->nullable()->after('fipe_brand');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'fipe_code',
                'fipe_model_year',
                'fipe_fuel',
                'fipe_reference_month',
                'fipe_value',
                'fipe_model',
                'fipe_brand',
                'fipe_score',
            ]);
        });
    }
};
