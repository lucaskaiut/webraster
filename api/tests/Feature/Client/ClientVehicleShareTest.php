<?php

namespace Tests\Feature\Client;

use App\Modules\Client\Models\Client;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientVehicleShareTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_operator_shares_vehicle_from_another_client_preserving_ownership(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $owner = Client::factory()->for($tenant)->create(['name' => 'Proprietário']);
        $authorized = Client::factory()->for($tenant)->create(['name' => 'Autorizado']);
        $vehicle = Vehicle::factory()->forClient($owner)->create(['plate' => 'SHA1R23']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->postJson("/api/clients/{$authorized->uuid}/shared-vehicles", [
            'vehicle_id' => $vehicle->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.vehicle.id', $vehicle->uuid)
            ->assertJsonPath('data.owner_client_id', $owner->uuid);

        $shareId = $response->json('data.id');

        // A propriedade do veículo não muda.
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->getKey(),
            'client_id' => $owner->getKey(),
        ]);

        $this->assertDatabaseHas('vehicle_shares', [
            'uuid' => $shareId,
            'vehicle_id' => $vehicle->getKey(),
            'client_id' => $authorized->getKey(),
        ]);

        // A listagem do cliente autorizado inclui o veículo compartilhado.
        $listing = $this->getJson("/api/vehicles?client_id={$authorized->uuid}")->assertOk();
        $plates = collect($listing->json('data'))->pluck('plate');

        $this->assertTrue($plates->contains('SHA1R23'));

        $this->getJson("/api/clients/{$authorized->uuid}/shared-vehicles")
            ->assertOk()
            ->assertJsonPath('data.0.id', $shareId);
    }

    public function test_vehicle_cannot_be_shared_with_its_own_client(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $owner = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($owner)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/clients/{$owner->uuid}/shared-vehicles", [
            'vehicle_id' => $vehicle->uuid,
        ])->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_duplicate_share_is_rejected(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $owner = Client::factory()->for($tenant)->create();
        $authorized = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($owner)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/clients/{$authorized->uuid}/shared-vehicles", [
            'vehicle_id' => $vehicle->uuid,
        ])->assertCreated();

        $this->postJson("/api/clients/{$authorized->uuid}/shared-vehicles", [
            'vehicle_id' => $vehicle->uuid,
        ])->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id']);

        $this->assertSame(1, VehicleShare::query()->count());
    }

    public function test_client_portal_user_sees_shared_vehicles(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $owner = Client::factory()->for($tenant)->create();
        $authorized = Client::factory()->for($tenant)->create([
            'name' => 'Autorizado',
            'email' => 'autorizado@example.com',
            'document' => '52998224725',
        ]);

        $ownVehicle = Vehicle::factory()->forClient($authorized)->create(['plate' => 'OWN1D23']);
        $sharedVehicle = Vehicle::factory()->forClient($owner)->create(['plate' => 'SHR1D23']);

        VehicleShare::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vehicle_id' => $sharedVehicle->getKey(),
            'client_id' => $authorized->getKey(),
        ]);

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $authorized->getKey()]));

        $plates = collect($this->getJson('/api/vehicles')->assertOk()->json('data'))->pluck('plate');

        $this->assertTrue($plates->contains('OWN1D23'));
        $this->assertTrue($plates->contains('SHR1D23'));
    }

    public function test_detach_removes_vehicle_from_authorized_client_listing(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $owner = Client::factory()->for($tenant)->create();
        $authorized = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($owner)->create(['plate' => 'DET1C23']);

        Sanctum::actingAs($this->createAdmin($tenant));

        $share = $this->postJson("/api/clients/{$authorized->uuid}/shared-vehicles", [
            'vehicle_id' => $vehicle->uuid,
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/clients/{$authorized->uuid}/shared-vehicles/{$share}")->assertOk();

        $plates = collect(
            $this->getJson("/api/vehicles?client_id={$authorized->uuid}")->assertOk()->json('data'),
        )->pluck('plate');

        $this->assertFalse($plates->contains('DET1C23'));
    }

    public function test_cannot_share_vehicle_from_another_tenant(): void
    {
        [$umbrella, $tenant] = $this->createOperationalChild();
        $otherTenant = $this->createChildTenant($umbrella);

        $authorized = Client::factory()->for($tenant)->create();
        $foreignOwner = Client::factory()->for($otherTenant)->create();
        $foreignVehicle = Vehicle::factory()->forClient($foreignOwner)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/clients/{$authorized->uuid}/shared-vehicles", [
            'vehicle_id' => $foreignVehicle->uuid,
        ])->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id']);
    }
}
