<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deslocamento de volta (cliente → base do técnico) calculado separadamente.
 * Rotas de mão única podem ter tempo/distância diferentes do trecho de ida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->unsignedInteger('estimated_return_travel_minutes')
                ->nullable()
                ->after('estimated_travel_minutes');
            $table->unsignedInteger('return_travel_distance_meters')
                ->nullable()
                ->after('travel_distance_meters');
        });

        // Ordens antigas não têm o trecho de volta: assume o mesmo tempo/distância da ida.
        DB::table('service_orders')->update([
            'estimated_return_travel_minutes' => DB::raw('estimated_travel_minutes'),
            'return_travel_distance_meters' => DB::raw('travel_distance_meters'),
        ]);
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn(['estimated_return_travel_minutes', 'return_travel_distance_meters']);
        });
    }
};
