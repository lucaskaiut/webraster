<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Situação (ativo/inativo) e módulos liberados de cada tenant, controlados
 * pelo umbrella. Tenants existentes seguem ativos e com todos os módulos,
 * para não alterar o acesso de quem já usa o sistema.
 */
return new class extends Migration
{
    private const ALL_MODULES = [
        'tracking',
        'geofences',
        'alerts',
        'reports',
        'service_orders',
        'contracts',
        'finance',
        'crm',
        'assistant',
    ];

    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('identifier');
            $table->json('modules')->nullable()->after('is_active');
        });

        DB::table('tenants')->update(['modules' => json_encode(self::ALL_MODULES)]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'modules']);
        });
    }
};
