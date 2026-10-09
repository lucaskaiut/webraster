<?php

namespace Tests\Feature\Alert;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Services\AlertEngine;
use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Services\GeofenceDetectionService;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class GeofenceAlertTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_entry_and_exit_create_geofence_alerts(): void
    {
        [$tenant, $client, $vehicle, $equipment] = $this->scenario();

        Geofence::factory()
            ->forClient($client)
            ->circle(-25.4284, -49.2733, 500)
            ->create(['name' => 'Base']);

        $base = CarbonImmutable::parse('2026-10-09 12:00:00');

        $this->process($tenant->getKey(), $client->getKey(), $vehicle, $equipment, [
            [-25.4400, -49.2733, $base, 1],
            [-25.4284, -49.2733, $base->addMinutes(1), 2],
            [-25.4400, -49.2733, $base->addMinutes(2), 3],
        ]);

        $alerts = Alert::query()
            ->withoutGlobalScopes()
            ->where('type', AlertType::GEOFENCE->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $alerts);
        $this->assertSame('Entrada na geocerca Base', $alerts[0]->title);
        $this->assertSame('entry', $alerts[0]->meta['event_type']);
        $this->assertSame('Saída da geocerca Base', $alerts[1]->title);
        $this->assertSame('exit', $alerts[1]->meta['event_type']);
        $this->assertSame(AlertType::GEOFENCE, $alerts[0]->type);
    }

    public function test_disabled_geofence_alert_config_does_not_create_alerts(): void
    {
        [$tenant, $client, $vehicle, $equipment] = $this->scenario();

        Geofence::factory()
            ->forClient($client)
            ->circle(-25.4284, -49.2733, 500)
            ->create(['name' => 'Base']);

        AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', AlertType::GEOFENCE->value)
            ->update(['is_enabled' => false]);

        $base = CarbonImmutable::parse('2026-10-09 13:00:00');

        $this->process($tenant->getKey(), $client->getKey(), $vehicle, $equipment, [
            [-25.4400, -49.2733, $base, 10],
            [-25.4284, -49.2733, $base->addMinutes(1), 11],
        ]);

        $this->assertSame(0, Alert::query()
            ->withoutGlobalScopes()
            ->where('type', AlertType::GEOFENCE->value)
            ->count());
    }

    /**
     * @return array{0: \App\Modules\Tenant\Models\Tenant, 1: Client, 2: Vehicle, 3: Equipment}
     */
    private function scenario(): array
    {
        [$tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create();

        return [$tenant, $client, $vehicle, $equipment];
    }

    /**
     * @param  list<array{0: float, 1: float, 2: CarbonImmutable, 3: int}>  $steps
     */
    private function process(
        int $tenantId,
        int $clientId,
        Vehicle $vehicle,
        Equipment $equipment,
        array $steps,
    ): void {
        $detection = app(GeofenceDetectionService::class);
        $engine = app(AlertEngine::class);

        foreach ($steps as [$latitude, $longitude, $recordedAt, $traccarId]) {
            $position = GpsPosition::query()->withoutGlobalScopes()->forceCreate([
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
                'vehicle_id' => $vehicle->getKey(),
                'equipment_id' => $equipment->getKey(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'recorded_at' => $recordedAt,
                'speed' => 40,
                'traccar_position_id' => $traccarId,
            ]);

            $detection->process($position);
            $engine->process($position);
        }
    }
}
