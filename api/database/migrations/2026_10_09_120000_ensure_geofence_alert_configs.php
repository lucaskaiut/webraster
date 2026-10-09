<?php

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Models\AlertState;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;

/**
 * Cataloga o novo alerta de entrada/saída de geocerca em todas as empresas,
 * clientes e veículos existentes. O padrão do sistema liga o alarme
 * (defaultEnabledTypes); padrões já configurados pela empresa prevalecem.
 */
return new class extends Migration
{
    public function up(): void
    {
        $configs = app(AlertConfigService::class);

        Tenant::query()->orderBy('id')->chunkById(100, function ($tenants) use ($configs): void {
            foreach ($tenants as $tenant) {
                $configs->ensureDefaults($tenant);
            }
        });

        Client::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($clients) use ($configs): void {
                foreach ($clients as $client) {
                    $configs->ensureClientDefaults($client);
                }
            });

        Vehicle::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($vehicles) use ($configs): void {
                foreach ($vehicles as $vehicle) {
                    $configs->ensureVehicleDefaults($vehicle);
                }
            });
    }

    public function down(): void
    {
        $type = AlertType::GEOFENCE->value;

        AlertState::query()->withoutGlobalScopes()->where('type', $type)->delete();
        AlertConfig::query()->withoutGlobalScopes()->where('type', $type)->delete();
    }
};
