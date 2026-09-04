<?php

namespace Tests\Feature\Driver;

use App\Modules\Client\Models\Client;
use App\Modules\Driver\Models\Driver;
use App\Modules\Shared\Support\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DriverCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_store_creates_driver_linked_to_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/drivers', [
            'client_id' => $client->uuid,
            'name' => 'João Motorista',
            'document' => Document::fakeCpf(),
            'cnh_number' => '12345678901',
            'cnh_expires_at' => '2030-01-15',
            'notes' => 'Turno diurno',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'João Motorista')
            ->assertJsonPath('data.client_id', $client->uuid)
            ->assertJsonPath('data.cnh_number', '12345678901');

        $this->assertDatabaseHas('drivers', [
            'tenant_id' => $tenant->getKey(),
            'client_id' => $client->getKey(),
            'name' => 'João Motorista',
        ]);
    }

    public function test_index_can_filter_by_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();

        Driver::factory()->forClient($clientA)->create(['name' => 'Motorista A']);
        Driver::factory()->forClient($clientB)->create(['name' => 'Motorista B']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/drivers?client_id='.$clientA->uuid)->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Motorista A'));
        $this->assertFalse($names->contains('Motorista B'));
    }

    public function test_update_and_destroy_driver(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $driver = Driver::factory()->forClient($client)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/drivers/{$driver->uuid}", [
            'name' => 'Nome Atualizado',
            'notes' => 'Atualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome Atualizado')
            ->assertJsonPath('data.notes', 'Atualizado');

        $this->deleteJson("/api/drivers/{$driver->uuid}")->assertOk();

        $this->assertSoftDeleted('drivers', ['id' => $driver->getKey()]);
    }
}
