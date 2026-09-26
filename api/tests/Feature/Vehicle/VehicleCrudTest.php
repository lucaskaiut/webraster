<?php

namespace Tests\Feature\Vehicle;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class VehicleCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_store_creates_vehicle_linked_to_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'abc1d23',
            'chassis' => '9BWZZZ377VT004251',
            'renavam' => '12345678901',
            'brand' => 'Volkswagen',
            'model' => 'Gol',
            'color' => 'Branco',
            'year' => 2022,
            'vehicle_type' => 2,
            'transmission' => 'automatic',
            'odometer' => 45000,
            'max_speed_kmh' => 80,
            'average_consumption' => 12.5,
            'tank_capacity' => 55,
        ])
            ->assertCreated()
            ->assertJsonPath('data.plate', 'ABC1D23')
            ->assertJsonPath('data.client_id', $client->uuid)
            ->assertJsonPath('data.brand', 'Volkswagen')
            ->assertJsonPath('data.vehicle_type', 2)
            ->assertJsonPath('data.transmission', 'automatic')
            ->assertJsonPath('data.odometer', 45000)
            ->assertJsonPath('data.max_speed_kmh', 80);

        $this->assertDatabaseHas('vehicles', [
            'tenant_id' => $tenant->getKey(),
            'client_id' => $client->getKey(),
            'plate' => 'ABC1D23',
            'vehicle_type' => 2,
        ]);
    }

    public function test_store_and_update_vehicle_alert_configs(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'ALR1T23',
            'alert_configs' => [
                [
                    'type' => 'speed',
                    'is_enabled' => true,
                    'notify_in_app' => true,
                    'notify_push' => false,
                    'notify_email' => true,
                ],
                [
                    'type' => 'sos',
                    'is_enabled' => false,
                    'notify_in_app' => true,
                    'notify_push' => true,
                    'notify_email' => false,
                ],
                [
                    'type' => 'device_alarm',
                    'alarm_code' => 'tow',
                    'is_enabled' => true,
                    'notify_in_app' => true,
                    'notify_push' => true,
                    'notify_email' => false,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.plate', 'ALR1T23');

        $vehicle = Vehicle::query()->where('plate', 'ALR1T23')->firstOrFail();

        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'speed',
            'is_enabled' => true,
            'notify_push' => false,
            'notify_email' => true,
        ]);

        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'sos',
            'is_enabled' => false,
        ]);

        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'device_alarm',
            'alarm_code' => 'tow',
            'is_enabled' => true,
        ]);

        // Tipos não enviados mantêm o padrão do veículo.
        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'jamming',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'battery',
            'is_enabled' => false,
        ]);

        $this->putJson("/api/vehicles/{$vehicle->uuid}", [
            'alert_configs' => [
                [
                    'type' => 'battery',
                    'is_enabled' => true,
                    'notify_in_app' => false,
                    'notify_push' => true,
                    'notify_email' => false,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'battery',
            'is_enabled' => true,
            'notify_in_app' => false,
        ]);
    }

    public function test_store_rejects_unknown_vehicle_type(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'abc1d23',
            'vehicle_type' => 123456,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_type');
    }

    public function test_index_can_filter_by_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();

        Vehicle::factory()->forClient($clientA)->create(['plate' => 'AAA1111']);
        Vehicle::factory()->forClient($clientB)->create(['plate' => 'BBB2222']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/vehicles?client_id='.$clientA->uuid)->assertOk();
        $plates = collect($response->json('data'))->pluck('plate');

        $this->assertTrue($plates->contains('AAA1111'));
        $this->assertFalse($plates->contains('BBB2222'));
    }

    public function test_update_and_destroy_vehicle(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'CCC3333']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/vehicles/{$vehicle->uuid}", [
            'plate' => 'ddd4e56',
            'color' => 'Preto',
            'vehicle_type' => 16,
            'transmission' => 'manual',
            'odometer' => 120000,
        ])
            ->assertOk()
            ->assertJsonPath('data.plate', 'DDD4E56')
            ->assertJsonPath('data.color', 'Preto')
            ->assertJsonPath('data.vehicle_type', 16)
            ->assertJsonPath('data.transmission', 'manual')
            ->assertJsonPath('data.odometer', 120000);

        $this->deleteJson("/api/vehicles/{$vehicle->uuid}")->assertOk();

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->getKey()]);
    }

    public function test_store_allows_reusing_plate_of_soft_deleted_vehicle(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $deleted = Vehicle::factory()->forClient($client)->create(['plate' => 'ABC1234']);
        $deleted->delete();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'abc1234',
        ])
            ->assertCreated()
            ->assertJsonPath('data.plate', 'ABC1234');
    }

    public function test_install_remove_and_swap_equipment_with_history(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipmentA = Equipment::factory()->forTenant($tenant)->create(['imei' => '111111111111111']);
        $equipmentB = Equipment::factory()->forTenant($tenant)->create(['imei' => '222222222222222']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/install", [
            'equipment_id' => $equipmentA->uuid,
            'notes' => 'Instalação inicial',
        ])
            ->assertOk()
            ->assertJsonPath('data.vehicle.equipment.id', $equipmentA->uuid)
            ->assertJsonPath('data.event.event', 'installation');

        $this->assertDatabaseHas('equipments', [
            'id' => $equipmentA->getKey(),
            'vehicle_id' => $vehicle->getKey(),
        ]);

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/swap", [
            'equipment_id' => $equipmentB->uuid,
            'notes' => 'Troca por defeito',
        ])
            ->assertOk()
            ->assertJsonPath('data.vehicle.equipment.id', $equipmentB->uuid)
            ->assertJsonPath('data.event.event', 'swap')
            ->assertJsonPath('data.event.previous_equipment_id', $equipmentA->uuid);

        $this->assertDatabaseHas('equipments', [
            'id' => $equipmentA->getKey(),
            'vehicle_id' => null,
        ]);
        $this->assertDatabaseHas('equipments', [
            'id' => $equipmentB->getKey(),
            'vehicle_id' => $vehicle->getKey(),
        ]);

        $this->postJson("/api/vehicles/{$vehicle->uuid}/equipment/remove", [
            'notes' => 'Retirada',
        ])
            ->assertOk()
            ->assertJsonPath('data.event.event', 'removal');

        $history = $this->getJson("/api/vehicles/{$vehicle->uuid}/equipment-history")->assertOk();
        $events = collect($history->json('data'))->pluck('event');

        $this->assertTrue($events->contains('installation'));
        $this->assertTrue($events->contains('swap'));
        $this->assertTrue($events->contains('removal'));
    }
}
