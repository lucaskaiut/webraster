<?php

namespace Tests\Feature\Alert;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Models\AlertState;
use App\Modules\Alert\Services\AlertStateStore;
use App\Modules\Client\Models\Client;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BatteryTamperMigrationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_copies_power_cut_configuration_into_battery_tamper(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        // Estado anterior à separação: bateria violada vinha do alarme powercut.
        AlertConfig::query()->withoutGlobalScopes()->where('type', AlertType::BATTERY_TAMPER->value)->delete();
        $this->deviceConfig($tenant->getKey(), null, $vehicle->getKey(), 'powercut', ['notify_email' => false]);
        $this->deviceConfig($tenant->getKey(), null, $vehicle->getKey(), 'tampering');
        AlertConfig::query()->withoutGlobalScopes()
            ->where('client_id', $client->getKey())
            ->where('type', AlertType::DEVICE_ALARM->value)
            ->update(['is_enabled' => false]);

        DB::table('tenants')->where('id', $tenant->getKey())->update([
            'vehicle_alert_defaults' => json_encode([
                ['type' => 'sos', 'alarm_code' => null, 'is_enabled' => true, 'notify_in_app' => true, 'notify_monitoring' => true, 'notify_push' => true, 'notify_email' => true],
                ['type' => 'device_alarm', 'alarm_code' => 'powercut', 'is_enabled' => true, 'notify_in_app' => true, 'notify_monitoring' => true, 'notify_push' => false, 'notify_email' => false],
                ['type' => 'device_alarm', 'alarm_code' => 'tow', 'is_enabled' => false, 'notify_in_app' => true, 'notify_monitoring' => true, 'notify_push' => true, 'notify_email' => false],
            ]),
        ]);

        AlertState::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'type' => AlertType::DEVICE_ALARM->value,
            'alert_config_id' => AlertStateStore::scopeIdForDeviceAlarm('powercut'),
            'is_active' => true,
            'meta' => ['alarm_code' => 'powercut'],
        ]);

        (require database_path('migrations/2026_10_30_000001_split_battery_tamper_alert.php'))->up();

        $vehicleConfig = AlertConfig::query()->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', AlertType::BATTERY_TAMPER->value)
            ->sole();
        $this->assertTrue($vehicleConfig->is_enabled);
        $this->assertFalse($vehicleConfig->notify_email);

        $this->assertFalse(AlertConfig::query()->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->whereIn('alarm_code', ['powercut', 'tampering', 'removing'])
            ->exists());

        // O cliente que silenciou os alarmes do dispositivo continua sem receber.
        $this->assertFalse((bool) AlertConfig::query()->withoutGlobalScopes()
            ->where('client_id', $client->getKey())
            ->whereNull('vehicle_id')
            ->where('type', AlertType::BATTERY_TAMPER->value)
            ->value('is_enabled'));

        $defaults = collect(json_decode((string) DB::table('tenants')->where('id', $tenant->getKey())->value('vehicle_alert_defaults'), true));
        $this->assertNull($defaults->firstWhere('alarm_code', 'powercut'));
        $this->assertNotNull($defaults->firstWhere('alarm_code', 'tow'));
        $tamper = $defaults->firstWhere('type', 'battery_tamper');
        $this->assertTrue($tamper['is_enabled']);
        $this->assertFalse($tamper['notify_push']);

        // Quem estava com alimentação cortada não recebe alerta repetido.
        $this->assertTrue((bool) AlertState::query()->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', AlertType::BATTERY_TAMPER->value)
            ->value('is_active'));
        $this->assertFalse(AlertState::query()->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', AlertType::DEVICE_ALARM->value)
            ->exists());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deviceConfig(int $tenantId, ?int $clientId, ?int $vehicleId, string $code, array $attributes = []): void
    {
        AlertConfig::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'vehicle_id' => $vehicleId,
            'alarm_code' => $code,
            'name' => $code,
            'type' => AlertType::DEVICE_ALARM->value,
            'is_enabled' => true,
            'notify_in_app' => true,
            'notify_monitoring' => true,
            'notify_push' => true,
            'notify_email' => true,
            ...$attributes,
        ]);
    }
}
