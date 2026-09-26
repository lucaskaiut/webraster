<?php

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explode os alarmes do dispositivo: cada código do protocolo passa a ter a
 * própria linha em `alert_configs` (campo `alarm_code`), permitindo ao
 * operador habilitar/desabilitar e escolher canais individualmente. Cria
 * também o canal `notify_monitoring`, que notifica os usuários do tenant
 * (In-app passa a notificar apenas os usuários do cliente). O alarme genérico
 * por veículo deixa de existir; códigos novos são catalogados automaticamente
 * quando aparecem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_configs', function (Blueprint $table): void {
            $table->string('alarm_code', 40)->nullable()->after('type');
            $table->boolean('notify_monitoring')->default(true)->after('notify_in_app');
            $table->index(
                ['tenant_id', 'vehicle_id', 'type', 'alarm_code'],
                'alert_configs_vehicle_alarm_code_index',
            );
        });

        AlertConfig::query()
            ->withoutGlobalScopes()
            ->whereNotNull('vehicle_id')
            ->whereNull('alarm_code')
            ->where('type', AlertType::DEVICE_ALARM->value)
            ->delete();

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
            ->whereNotNull('alarm_code')
            ->delete();

        Schema::table('alert_configs', function (Blueprint $table): void {
            $table->dropIndex('alert_configs_vehicle_alarm_code_index');
            $table->dropColumn('alarm_code');
            $table->dropColumn('notify_monitoring');
        });
    }
};
