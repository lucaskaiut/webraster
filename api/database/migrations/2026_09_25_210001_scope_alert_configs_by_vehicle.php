<?php

use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;

/**
 * O modelo de alertas passa a ser por veículo, definido pelo operador no
 * cadastro do veículo. A configuração por cliente vira apenas um silenciador:
 * as linhas existentes passam a valer como "receber" e cada veículo ganha
 * suas configurações padrão (alertas críticos habilitados).
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

        Vehicle::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($vehicles): void {
                foreach ($vehicles as $vehicle) {
                    app(AlertConfigService::class)->ensureVehicleDefaults($vehicle);
                }
            });
    }

    public function down(): void
    {
        AlertConfig::query()
            ->withoutGlobalScopes()
            ->whereNotNull('vehicle_id')
            ->delete();
    }
};
