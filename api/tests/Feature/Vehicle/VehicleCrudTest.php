<?php

namespace Tests\Feature\Vehicle;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\GpsPosition;
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

    public function test_store_accepts_multiple_device_alarm_configs(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $configs = [
            [
                'type' => 'speed',
                'is_enabled' => true,
                'notify_in_app' => true,
                'notify_push' => true,
                'notify_email' => false,
            ],
        ];

        foreach (['accident', 'powercut', 'removing', 'tampering'] as $code) {
            $configs[] = [
                'type' => 'device_alarm',
                'alarm_code' => $code,
                'is_enabled' => true,
                'notify_in_app' => true,
                'notify_push' => true,
                'notify_email' => true,
            ];
        }

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'DEV1C23',
            'alert_configs' => $configs,
        ])->assertCreated();

        $vehicle = Vehicle::query()->where('plate', 'DEV1C23')->firstOrFail();

        foreach (['accident', 'powercut', 'removing', 'tampering'] as $code) {
            $this->assertDatabaseHas('alert_configs', [
                'vehicle_id' => $vehicle->getKey(),
                'type' => 'device_alarm',
                'alarm_code' => $code,
                'is_enabled' => true,
                'notify_email' => true,
            ]);
        }
    }

    public function test_store_rejects_duplicate_alert_configs(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'DUP1C23',
            'alert_configs' => [
                ['type' => 'speed'],
                ['type' => 'speed'],
                ['type' => 'device_alarm', 'alarm_code' => 'tow'],
                ['type' => 'device_alarm', 'alarm_code' => 'TOW'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alert_configs.1.type', 'alert_configs.3.type']);
    }

    public function test_update_rejects_duplicate_alert_configs(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create(['plate' => 'UPD1C23']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/vehicles/{$vehicle->uuid}", [
            'alert_configs' => [
                ['type' => 'device_alarm', 'alarm_code' => 'tow'],
                ['type' => 'device_alarm', 'alarm_code' => 'TOW'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alert_configs.1.type']);
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

    public function test_index_can_search_by_client_name(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create(['name' => 'Transportadora Horizonte']);
        $clientB = Client::factory()->for($tenant)->create(['name' => 'Logística Sul']);

        Vehicle::factory()->forClient($clientA)->create(['plate' => 'AAA1111']);
        Vehicle::factory()->forClient($clientB)->create(['plate' => 'BBB2222']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/vehicles?search='.urlencode('Horizonte'))->assertOk();
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

    public function test_store_can_install_equipment_on_create(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $equipment = Equipment::factory()->forTenant($tenant)->create(['imei' => '333333333333333']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'EQP1D23',
            'equipment_id' => $equipment->uuid,
            'equipment_notes' => 'Instalação no cadastro',
        ])
            ->assertCreated()
            ->assertJsonPath('data.plate', 'EQP1D23')
            ->assertJsonPath('data.equipment.id', $equipment->uuid);

        $this->assertDatabaseHas('equipments', [
            'id' => $equipment->getKey(),
            'vehicle_id' => Vehicle::query()->where('plate', 'EQP1D23')->value('id'),
        ]);
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

    public function test_index_searches_by_equipment_imei(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $target = Vehicle::factory()->forClient($client)->create(['plate' => 'TRG1T23']);
        Equipment::factory()->assignedTo($target)->create(['imei' => '359633100009999']);

        $other = Vehicle::factory()->forClient($client)->create(['plate' => 'OUT1R23']);
        Equipment::factory()->assignedTo($other)->create(['imei' => '359633100008888']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/vehicles?search=359633100009999')->assertOk();
        $plates = collect($response->json('data'))->pluck('plate');

        $this->assertTrue($plates->contains('TRG1T23'));
        $this->assertFalse($plates->contains('OUT1R23'));
    }

    public function test_index_accepts_all_as_per_page(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Vehicle::factory()->forClient($client)->create(['plate' => 'ALL1A23']);
        Vehicle::factory()->forClient($client)->create(['plate' => 'ALL2B23']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/vehicles?per_page=all')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_sorts_by_last_connection_and_last_position(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $stale = Vehicle::factory()->forClient($client)->create(['plate' => 'SORT1A1']);
        $staleEquipment = Equipment::factory()->assignedTo($stale)->create([
            'traccar_last_update' => now()->subHours(5),
        ]);
        GpsPosition::factory()->forVehicle($stale)->create([
            'equipment_id' => $staleEquipment->getKey(),
            'recorded_at' => now()->subDays(2),
        ]);

        $recent = Vehicle::factory()->forClient($client)->create(['plate' => 'SORT2B2']);
        $recentEquipment = Equipment::factory()->assignedTo($recent)->create([
            'traccar_last_update' => now()->subHour(),
        ]);
        GpsPosition::factory()->forVehicle($recent)->create([
            'equipment_id' => $recentEquipment->getKey(),
            'recorded_at' => now()->subMinutes(10),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $byConnection = collect(
            $this->getJson('/api/vehicles?sort=last_connection:desc')->assertOk()->json('data'),
        )->pluck('plate');

        $this->assertSame(['SORT2B2', 'SORT1A1'], $byConnection->all());

        $byPosition = collect(
            $this->getJson('/api/vehicles?sort=last_position:asc')->assertOk()->json('data'),
        )->pluck('plate');

        $this->assertSame(['SORT1A1', 'SORT2B2'], $byPosition->all());
    }

    public function test_index_filters_by_last_signal_buckets(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        $online = Vehicle::factory()->forClient($client)->create(['plate' => 'ONL1N23']);
        Equipment::factory()->assignedTo($online)->create(['traccar_last_update' => now()->subMinutes(2)]);

        $offline24 = Vehicle::factory()->forClient($client)->create(['plate' => 'OF12423']);
        Equipment::factory()->assignedTo($offline24)->create(['traccar_last_update' => now()->subHours(10)]);

        $offline36 = Vehicle::factory()->forClient($client)->create(['plate' => 'OF23623']);
        Equipment::factory()->assignedTo($offline36)->create(['traccar_last_update' => now()->subHours(30)]);

        $offlineLong = Vehicle::factory()->forClient($client)->create(['plate' => 'OF44823']);
        Equipment::factory()->assignedTo($offlineLong)->create(['traccar_last_update' => now()->subHours(48)]);

        // Sem nenhum sinal conta como +36h.
        $noSignal = Vehicle::factory()->forClient($client)->create(['plate' => 'NOS1G23']);
        Equipment::factory()->assignedTo($noSignal)->create(['traccar_last_update' => null]);

        // Inativo não entra nos filtros de sinal (mesmo recorte do dashboard).
        $inactive = Vehicle::factory()->forClient($client)->create([
            'plate' => 'INA1T23',
            'is_active' => false,
        ]);
        Equipment::factory()->assignedTo($inactive)->create(['traccar_last_update' => now()->subHours(48)]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $plates = function (string $signal): array {
            return collect(
                $this->getJson("/api/vehicles?last_signal={$signal}&per_page=all")
                    ->assertOk()
                    ->json('data'),
            )->pluck('plate')->all();
        };

        $this->assertSame(['ONL1N23'], $plates('online'));
        $this->assertSame(['OF12423'], $plates('offline_up_to_24h'));
        $this->assertSame(['OF23623'], $plates('offline_24h_to_36h'));
        $this->assertEqualsCanonicalizing(['OF44823', 'NOS1G23'], $plates('offline_over_36h'));
    }

    public function test_export_returns_xlsx_respecting_filters(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Vehicle::factory()->forClient($client)->create(['plate' => 'EXP1T23']);
        Vehicle::factory()->forClient($client)->create(['plate' => 'NAO1E23']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->get('/api/vehicles/export?search=EXP1T23')
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $content = $response->streamedContent();

        $this->assertStringStartsWith('PK', $content);
        $this->assertStringContainsString('veiculos.xlsx', (string) $response->headers->get('content-disposition'));
    }
}
