<?php

namespace Tests\Feature\Geofence;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Geofence\Enums\GeofenceEventType;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Models\GeofenceEvent;
use App\Modules\Geofence\Services\GeofenceDetectionService;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class GeofenceDetectionTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_entry_exit_state_machine_and_no_duplicate_while_inside(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create();

        $geofence = Geofence::factory()
            ->forClient($client)
            ->circle(-25.4284, -49.2733, 500)
            ->create(['name' => 'Base']);

        $detection = app(GeofenceDetectionService::class);
        $base = CarbonImmutable::parse('2026-09-05 12:00:00');

        $outside1 = $this->position($tenant->getKey(), $client->getKey(), $vehicle->getKey(), $equipment->getKey(), -25.4400, -49.2733, $base, 1);
        $outside2 = $this->position($tenant->getKey(), $client->getKey(), $vehicle->getKey(), $equipment->getKey(), -25.4390, -49.2733, $base->addMinutes(1), 2);
        $inside1 = $this->position($tenant->getKey(), $client->getKey(), $vehicle->getKey(), $equipment->getKey(), -25.4284, -49.2733, $base->addMinutes(2), 3);
        $inside2 = $this->position($tenant->getKey(), $client->getKey(), $vehicle->getKey(), $equipment->getKey(), -25.4285, -49.2730, $base->addMinutes(3), 4);
        $outside3 = $this->position($tenant->getKey(), $client->getKey(), $vehicle->getKey(), $equipment->getKey(), -25.4400, -49.2733, $base->addMinutes(4), 5);

        foreach ([$outside1, $outside2, $inside1, $inside2, $outside3] as $position) {
            $detection->process($position);
        }

        $events = GeofenceEvent::query()->withoutGlobalScopes()->orderBy('recorded_at')->get();

        $this->assertCount(2, $events);
        $this->assertSame(GeofenceEventType::ENTRY, $events[0]->type);
        $this->assertSame(GeofenceEventType::EXIT, $events[1]->type);
        $this->assertTrue($events[0]->gps_position_id === $inside1->getKey());
        $this->assertTrue($events[1]->gps_position_id === $outside3->getKey());
    }

    public function test_duplicate_position_processing_does_not_create_duplicate_events(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create();
        Geofence::factory()->forClient($client)->circle(-25.4284, -49.2733, 500)->create();

        $detection = app(GeofenceDetectionService::class);
        $outside = $this->position($tenant->getKey(), $client->getKey(), $vehicle->getKey(), $equipment->getKey(), -25.4400, -49.2733, CarbonImmutable::parse('2026-09-05 13:00:00'), 10);
        $inside = $this->position($tenant->getKey(), $client->getKey(), $vehicle->getKey(), $equipment->getKey(), -25.4284, -49.2733, CarbonImmutable::parse('2026-09-05 13:01:00'), 11);

        $detection->process($outside);
        $detection->process($inside);
        $detection->process($inside);
        $detection->process($inside);

        $this->assertSame(1, GeofenceEvent::query()->withoutGlobalScopes()->count());
        $this->assertSame(GeofenceEventType::ENTRY, GeofenceEvent::query()->withoutGlobalScopes()->first()->type);
    }

    public function test_tenant_isolation_for_geofence_crud_and_events(): void
    {
        [, $tenantA] = $this->createOperationalChild();
        [, $tenantB] = $this->createOperationalChild();

        $clientA = Client::factory()->for($tenantA)->create();
        $clientB = Client::factory()->for($tenantB)->create();

        $geofenceA = Geofence::factory()->forClient($clientA)->circle(-25.4284, -49.2733, 500)->create(['name' => 'A']);
        $geofenceB = Geofence::factory()->forClient($clientB)->circle(-25.4284, -49.2733, 500)->create(['name' => 'B']);

        Sanctum::actingAs($this->createAdmin($tenantA));

        $this->getJson('/api/geofences')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'A');

        $this->getJson('/api/geofences/'.$geofenceB->uuid)->assertNotFound();
        $this->deleteJson('/api/geofences/'.$geofenceB->uuid)->assertNotFound();
        $this->getJson('/api/geofence-events')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_crud_creates_circle_and_polygon(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/geofences', [
            'client_id' => $client->uuid,
            'name' => 'Círculo Central',
            'type' => 'circle',
            'center_latitude' => -25.4284,
            'center_longitude' => -49.2733,
            'radius_meters' => 300,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'circle')
            ->assertJsonPath('data.radius_meters', 300);

        $this->postJson('/api/geofences', [
            'client_id' => $client->uuid,
            'name' => 'Polígono Parque',
            'type' => 'polygon',
            'geometry' => [
                ['latitude' => -25.4300, 'longitude' => -49.2750],
                ['latitude' => -25.4300, 'longitude' => -49.2700],
                ['latitude' => -25.4260, 'longitude' => -49.2700],
                ['latitude' => -25.4260, 'longitude' => -49.2750],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'polygon');

        $this->postJson('/api/geofences', [
            'client_id' => $client->uuid,
            'name' => 'Inválido',
            'type' => 'polygon',
            'geometry' => [
                ['latitude' => -25.4300, 'longitude' => -49.2750],
                ['latitude' => -25.4300, 'longitude' => -49.2700],
            ],
        ])->assertUnprocessable();
    }

    private function position(
        int $tenantId,
        int $clientId,
        int $vehicleId,
        int $equipmentId,
        float $lat,
        float $lng,
        CarbonImmutable $recordedAt,
        int $traccarId,
    ): GpsPosition {
        return GpsPosition::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'vehicle_id' => $vehicleId,
            'equipment_id' => $equipmentId,
            'latitude' => $lat,
            'longitude' => $lng,
            'recorded_at' => $recordedAt,
            'speed' => 40,
            'traccar_position_id' => $traccarId,
        ]);
    }
}
