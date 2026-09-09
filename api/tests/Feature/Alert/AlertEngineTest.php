<?php

namespace Tests\Feature\Alert;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Alert\Services\AlertEngine;
use App\Modules\Alert\Services\OfflineAlertService;
use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AlertEngineTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_speed_alert_requires_duration_and_avoids_duplicates(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create();
        app(AlertConfigService::class)->ensureDefaults($tenant);

        $config = app(AlertConfigService::class)->findForTenant($tenant->getKey(), AlertType::SPEED);
        $config->forceFill([
            'settings' => ['speed_limit_kmh' => 80, 'min_duration_seconds' => 60],
            'notify_email' => false,
        ])->save();

        $engine = app(AlertEngine::class);
        $base = CarbonImmutable::parse('2026-09-05 10:00:00');

        // 79 km/h ≈ 42.66 knots — below limit
        $engine->process($this->pos($tenant, $client, $vehicle, 42.6, $base, 1));
        $this->assertSame(0, Alert::query()->withoutGlobalScopes()->count());

        // 100 km/h ≈ 54 knots — start exceeding
        $engine->process($this->pos($tenant, $client, $vehicle, 54, $base->addSeconds(10), 2));
        $this->assertSame(0, Alert::query()->withoutGlobalScopes()->count());

        // still exceeding but < 60s
        $engine->process($this->pos($tenant, $client, $vehicle, 55, $base->addSeconds(50), 3));
        $this->assertSame(0, Alert::query()->withoutGlobalScopes()->count());

        // exceeding for >= 60s
        $engine->process($this->pos($tenant, $client, $vehicle, 56, $base->addSeconds(80), 4));
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'speed')->count());

        // continue exceeding — no duplicate
        $engine->process($this->pos($tenant, $client, $vehicle, 57, $base->addSeconds(100), 5));
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'speed')->count());
    }

    public function test_ignition_sos_battery_and_jamming_transitions(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create();
        app(AlertConfigService::class)->ensureDefaults($tenant);

        $engine = app(AlertEngine::class);
        $base = CarbonImmutable::parse('2026-09-05 11:00:00');

        $p1 = $this->pos($tenant, $client, $vehicle, 10, $base, 10, ignition: false, battery: 50);
        $engine->process($p1);

        $p2 = $this->pos($tenant, $client, $vehicle, 10, $base->addMinute(), 11, ignition: true, battery: 50);
        $engine->process($p2);
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'ignition_on')->count());

        $p3 = $this->pos($tenant, $client, $vehicle, 10, $base->addMinutes(2), 12, ignition: false, battery: 50);
        $engine->process($p3);
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'ignition_off')->count());

        $p4 = $this->pos($tenant, $client, $vehicle, 10, $base->addMinutes(3), 13, ignition: false, battery: 10, attributes: ['sos' => true]);
        $engine->process($p4);
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'sos')->count());
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'battery')->count());

        $p5 = $this->pos($tenant, $client, $vehicle, 10, $base->addMinutes(4), 14, ignition: false, battery: 10, attributes: ['sos' => true, 'jamming' => true]);
        $engine->process($p5);
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'jamming')->count());

        // duplicates suppressed while conditions remain active
        $engine->process($this->pos($tenant, $client, $vehicle, 10, $base->addMinutes(5), 15, ignition: false, battery: 9, attributes: ['sos' => true, 'jamming' => true]));
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'sos')->count());
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'battery')->count());
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'jamming')->count());
    }

    public function test_offline_and_online_cycle(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create();
        app(AlertConfigService::class)->ensureDefaults($tenant);

        $config = app(AlertConfigService::class)->findForTenant($tenant->getKey(), AlertType::OFFLINE);
        $config->forceFill([
            'settings' => ['offline_minutes' => 5],
            'notify_email' => false,
        ])->save();

        $old = CarbonImmutable::parse('2026-09-05 08:00:00');
        $this->pos($tenant, $client, $vehicle, 10, $old, 100);

        $offline = app(OfflineAlertService::class)->evaluateVehicle(
            $vehicle,
            CarbonImmutable::parse('2026-09-05 08:10:00'),
        );
        $this->assertCount(1, $offline);
        $this->assertSame(AlertType::OFFLINE, $offline->first()->type);

        // second scan — no duplicate
        $again = app(OfflineAlertService::class)->evaluateVehicle(
            $vehicle,
            CarbonImmutable::parse('2026-09-05 08:20:00'),
        );
        $this->assertCount(0, $again);

        $fresh = $this->pos($tenant, $client, $vehicle, 10, CarbonImmutable::parse('2026-09-05 08:25:00'), 101);
        $online = app(OfflineAlertService::class)->markOnline($vehicle, $fresh);
        $this->assertCount(1, $online);
        $this->assertSame(AlertType::ONLINE, $online->first()->type);
    }

    public function test_vehicle_scoped_config_does_not_fire_for_other_vehicles(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicleA = Vehicle::factory()->forClient($client)->create();
        $vehicleB = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicleA)->create();
        Equipment::factory()->assignedTo($vehicleB)->create();

        app(AlertConfigService::class)->create([
            'type' => AlertType::SOS,
            'vehicle_id' => $vehicleA->getKey(),
            'is_enabled' => true,
            'notify_in_app' => true,
            'notify_email' => false,
        ]);

        $engine = app(AlertEngine::class);
        $base = CarbonImmutable::parse('2026-09-05 12:00:00');

        $engine->process($this->pos($tenant, $client, $vehicleB, 10, $base, 200, attributes: ['sos' => true]));
        $this->assertSame(0, Alert::query()->withoutGlobalScopes()->where('type', 'sos')->count());

        $engine->process($this->pos($tenant, $client, $vehicleA, 10, $base->addMinute(), 201, attributes: ['sos' => true]));
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'sos')->count());
    }

    public function test_client_scoped_config_applies_to_client_vehicles_only(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $vehicleA = Vehicle::factory()->forClient($clientA)->create();
        $vehicleB = Vehicle::factory()->forClient($clientB)->create();
        Equipment::factory()->assignedTo($vehicleA)->create();
        Equipment::factory()->assignedTo($vehicleB)->create();

        app(AlertConfigService::class)->create([
            'type' => AlertType::IGNITION_ON,
            'client_id' => $clientA->getKey(),
            'is_enabled' => true,
            'notify_in_app' => true,
            'notify_email' => false,
        ]);

        $engine = app(AlertEngine::class);
        $base = CarbonImmutable::parse('2026-09-05 13:00:00');

        $engine->process($this->pos($tenant, $clientB, $vehicleB, 10, $base, 300, ignition: false));
        $engine->process($this->pos($tenant, $clientB, $vehicleB, 10, $base->addMinute(), 301, ignition: true));
        $this->assertSame(0, Alert::query()->withoutGlobalScopes()->where('type', 'ignition_on')->count());

        $engine->process($this->pos($tenant, $clientA, $vehicleA, 10, $base->addMinutes(2), 302, ignition: false));
        $engine->process($this->pos($tenant, $clientA, $vehicleA, 10, $base->addMinutes(3), 303, ignition: true));
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'ignition_on')->count());
    }

    public function test_can_create_scoped_alert_config_via_api(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/alert-configs', [
            'type' => 'speed',
            'vehicle_id' => $vehicle->uuid,
            'name' => 'Limite frota',
            'settings' => ['speed_limit_kmh' => 100, 'min_duration_seconds' => 30],
            'notify_email' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'speed')
            ->assertJsonPath('data.scope', 'vehicle')
            ->assertJsonPath('data.vehicle.id', $vehicle->uuid)
            ->assertJsonPath('data.settings.speed_limit_kmh', 100);

        $this->postJson('/api/alert-configs', [
            'type' => 'battery',
            'client_id' => $client->uuid,
            'vehicle_id' => $vehicle->uuid,
        ])->assertUnprocessable();
    }

    public function test_device_alarm_power_cut_and_restore(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        Equipment::factory()->assignedTo($vehicle)->create();
        app(AlertConfigService::class)->ensureDefaults($tenant);

        $engine = app(AlertEngine::class);
        $base = CarbonImmutable::parse('2026-09-05 14:00:00');

        $engine->process($this->pos($tenant, $client, $vehicle, 0, $base, 400, attributes: ['alarm' => 'powerCut']));
        $alert = Alert::query()->withoutGlobalScopes()->where('type', 'device_alarm')->first();
        $this->assertNotNull($alert);
        $this->assertSame('Alimentação cortada', $alert->title);
        $this->assertSame('powercut', $alert->meta['alarm_code'] ?? null);

        // duplicate suppressed while alarm remains active
        $engine->process($this->pos($tenant, $client, $vehicle, 0, $base->addMinute(), 401, attributes: ['alarm' => 'powerCut']));
        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('type', 'device_alarm')->count());

        // restored clears state; new alarm can fire again later
        $engine->process($this->pos($tenant, $client, $vehicle, 0, $base->addMinutes(2), 402, attributes: ['alarm' => 'powerRestored']));
        $engine->process($this->pos($tenant, $client, $vehicle, 0, $base->addMinutes(3), 403, attributes: ['alarm' => 'powerCut']));
        $this->assertSame(2, Alert::query()->withoutGlobalScopes()->where('type', 'device_alarm')->count());
    }

    public function test_tenant_isolation_and_config_api(): void
    {
        [, $tenantA] = $this->createOperationalChild();
        [, $tenantB] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenantA)->create();
        $vehicleA = Vehicle::factory()->forClient($clientA)->create();
        Equipment::factory()->assignedTo($vehicleA)->create();
        app(AlertConfigService::class)->ensureDefaults($tenantA);
        app(AlertConfigService::class)->ensureDefaults($tenantB);

        $alert = new Alert;
        $alert->forceFill([
            'tenant_id' => $tenantA->getKey(),
            'client_id' => $clientA->getKey(),
            'vehicle_id' => $vehicleA->getKey(),
            'type' => AlertType::SOS,
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'SOS',
            'occurred_at' => now(),
        ])->save();

        Sanctum::actingAs($this->createAdmin($tenantB));
        $this->getJson('/api/alerts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/alerts/'.$alert->uuid)->assertNotFound();

        Sanctum::actingAs($this->createAdmin($tenantA));
        $this->getJson('/api/alerts')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/alert-configs')->assertOk();
        $configs = $this->getJson('/api/alert-configs')->json('data');
        $speedId = collect($configs)->firstWhere('type', 'speed')['id'];
        $this->putJson('/api/alert-configs/'.$speedId, [
            'settings' => ['speed_limit_kmh' => 90],
            'notify_email' => true,
        ])->assertOk()->assertJsonPath('data.settings.speed_limit_kmh', 90);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pos(
        $tenant,
        $client,
        $vehicle,
        float $speedKnots,
        CarbonImmutable $at,
        int $traccarId,
        ?bool $ignition = null,
        ?float $battery = null,
        array $attributes = [],
    ): GpsPosition {
        $equipment = $vehicle->equipment ?? Equipment::factory()->assignedTo($vehicle)->create();

        return GpsPosition::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'client_id' => $client->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'equipment_id' => $equipment->getKey(),
            'latitude' => -25.4284,
            'longitude' => -49.2733,
            'recorded_at' => $at,
            'speed' => $speedKnots,
            'ignition' => $ignition,
            'battery' => $battery,
            'attributes' => $attributes,
            'traccar_position_id' => $traccarId,
        ]);
    }
}
