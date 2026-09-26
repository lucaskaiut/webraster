<?php

use App\Modules\Alert\Models\AlertConfig;
use Illuminate\Database\Migrations\Migration;

/**
 * O modelo de alertas passa a ser por veículo, definido pelo operador no
 * cadastro do veículo. A configuração por cliente vira apenas um silenciador:
 * as linhas existentes passam a valer como "receber". As configurações por
 * veículo são criadas na migration seguinte, quando `alarm_code` já existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        AlertConfig::query()
            ->withoutGlobalScopes()
            ->whereNull('vehicle_id')
            ->whereNotNull('client_id')
            ->update(['is_enabled' => true]);
    }

    public function down(): void
    {
        //
    }
};
