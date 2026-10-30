<?php

use App\Modules\Alert\Services\AlertStateStore;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Separa "Bateria violada" (powercut, tampering, removing) dos alarmes
 * genéricos do rastreador em um tipo próprio, preservando o comportamento
 * atual: cada configuração nova copia a do alarme `powercut` de onde veio.
 */
return new class extends Migration
{
    private const TYPE = 'battery_tamper';

    private const CODES = ['powercut', 'tampering', 'removing'];

    private const CHANNELS = ['is_enabled', 'notify_in_app', 'notify_monitoring', 'notify_push', 'notify_email'];

    public function up(): void
    {
        $this->migrateVehicleConfigs();
        $this->migrateClientConfigs();
        $this->migrateTenantDefaults();
        $this->migrateActiveStates();
    }

    public function down(): void
    {
        //
    }

    /**
     * Uma configuração por veículo, copiada do `powercut` do veículo
     * (ou ligada com todos os canais, o padrão de alarme crítico).
     */
    private function migrateVehicleConfigs(): void
    {
        $vehicles = DB::table('alert_configs')
            ->whereNotNull('vehicle_id')
            ->select('tenant_id', 'vehicle_id')
            ->distinct()
            ->get();

        foreach ($vehicles as $vehicle) {
            $exists = DB::table('alert_configs')
                ->where('vehicle_id', $vehicle->vehicle_id)
                ->where('type', self::TYPE)
                ->exists();

            if (! $exists) {
                $source = DB::table('alert_configs')
                    ->where('vehicle_id', $vehicle->vehicle_id)
                    ->where('type', 'device_alarm')
                    ->where('alarm_code', 'powercut')
                    ->orderBy('id')
                    ->first();

                $this->insertConfig($vehicle->tenant_id, null, $vehicle->vehicle_id, $source);
            }
        }

        DB::table('alert_configs')
            ->whereNotNull('vehicle_id')
            ->where('type', 'device_alarm')
            ->whereIn('alarm_code', self::CODES)
            ->delete();
    }

    /**
     * Silenciamento do cliente no portal: até aqui o powercut seguia o
     * "Alarme do dispositivo" do cliente.
     */
    private function migrateClientConfigs(): void
    {
        $clients = DB::table('alert_configs')
            ->whereNotNull('client_id')
            ->whereNull('vehicle_id')
            ->select('tenant_id', 'client_id')
            ->distinct()
            ->get();

        foreach ($clients as $client) {
            $exists = DB::table('alert_configs')
                ->where('client_id', $client->client_id)
                ->whereNull('vehicle_id')
                ->where('type', self::TYPE)
                ->exists();

            if ($exists) {
                continue;
            }

            $source = DB::table('alert_configs')
                ->where('client_id', $client->client_id)
                ->whereNull('vehicle_id')
                ->where('type', 'device_alarm')
                ->orderBy('id')
                ->first();

            $this->insertConfig($client->tenant_id, $client->client_id, null, $source);
        }
    }

    private function migrateTenantDefaults(): void
    {
        $tenants = DB::table('tenants')
            ->whereNotNull('vehicle_alert_defaults')
            ->get(['id', 'vehicle_alert_defaults']);

        foreach ($tenants as $tenant) {
            $defaults = json_decode((string) $tenant->vehicle_alert_defaults, true);

            if (! is_array($defaults) || $defaults === []) {
                continue;
            }

            $source = null;
            $hasType = false;
            $kept = [];

            foreach ($defaults as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $code = strtolower(trim((string) ($entry['alarm_code'] ?? '')));

                if (($entry['type'] ?? null) === 'device_alarm' && in_array($code, self::CODES, true)) {
                    $source = $code === 'powercut' ? $entry : ($source ?? $entry);

                    continue;
                }

                $hasType = $hasType || ($entry['type'] ?? null) === self::TYPE;
                $kept[] = $entry;
            }

            if (! $hasType && $source !== null) {
                $kept[] = [
                    'type' => self::TYPE,
                    'alarm_code' => null,
                    ...array_intersect_key($source, array_flip(self::CHANNELS)),
                ];
            }

            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['vehicle_alert_defaults' => json_encode(array_values($kept))]);
        }
    }

    /**
     * Veículos com alimentação cortada agora não devem receber um alerta
     * repetido só porque o estado passou a ser de outro tipo.
     */
    private function migrateActiveStates(): void
    {
        $scopes = array_map(
            fn (string $code) => AlertStateStore::scopeIdForDeviceAlarm($code),
            self::CODES,
        );

        $states = DB::table('alert_states')
            ->where('type', 'device_alarm')
            ->whereIn('alert_config_id', $scopes)
            ->get();

        foreach ($states->groupBy('vehicle_id') as $vehicleId => $vehicleStates) {
            $active = $vehicleStates->firstWhere('is_active', 1);

            if ($active !== null) {
                DB::table('alert_states')->updateOrInsert(
                    ['vehicle_id' => $vehicleId, 'type' => self::TYPE, 'alert_config_id' => 0],
                    [
                        'tenant_id' => $active->tenant_id,
                        'is_active' => true,
                        'started_at' => $active->started_at,
                        'last_gps_position_id' => $active->last_gps_position_id,
                        'last_recorded_at' => $active->last_recorded_at,
                        'meta' => $active->meta,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        DB::table('alert_states')
            ->where('type', 'device_alarm')
            ->whereIn('alert_config_id', $scopes)
            ->delete();
    }

    private function insertConfig(int $tenantId, ?int $clientId, ?int $vehicleId, ?object $source): void
    {
        DB::table('alert_configs')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'vehicle_id' => $vehicleId,
            'alarm_code' => null,
            'name' => 'Bateria violada',
            'type' => self::TYPE,
            'is_enabled' => $source?->is_enabled ?? true,
            'notify_in_app' => $source?->notify_in_app ?? true,
            'notify_monitoring' => $source?->notify_monitoring ?? true,
            'notify_push' => $source?->notify_push ?? true,
            'notify_email' => $source?->notify_email ?? true,
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
