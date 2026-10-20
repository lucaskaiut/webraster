<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceOrderInstallationLocationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'traccar.enabled' => false,
            'scheduling.geocoding.enabled' => false,
            'scheduling.routing.enabled' => false,
        ]);
    }

    public function test_stores_and_replaces_installation_location_with_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/old-location.jpg', 'old-image');

        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);

        Sanctum::actingAs($admin);

        $orderId = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'installation_location' => 'Parede da sala, ao lado da TV.',
            'installation_location_photo_path' => 'uploads/old-location.jpg',
        ])->assertCreated()
            ->assertJsonPath('data.installation_location', 'Parede da sala, ao lado da TV.')
            ->assertJsonPath('data.installation_location_photo_path', 'uploads/old-location.jpg')
            ->json('data.id');

        $this->putJson("/api/service-orders/{$orderId}", [
            'installation_location' => 'Painel elétrico da entrada.',
            'installation_location_photo_path' => 'uploads/new-location.jpg',
        ])
            ->assertOk()
            ->assertJsonPath('data.installation_location', 'Painel elétrico da entrada.')
            ->assertJsonPath('data.installation_location_photo_path', 'uploads/new-location.jpg');

        Storage::disk('public')->assertMissing('uploads/old-location.jpg');
        $this->assertDatabaseHas('service_orders', [
            'uuid' => $orderId,
            'installation_location' => 'Painel elétrico da entrada.',
            'installation_location_photo_path' => 'uploads/new-location.jpg',
        ]);
    }

    public function test_rejects_photo_path_outside_uploads_directory(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'installation_location_photo_path' => 'https://example.com/location.jpg',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('installation_location_photo_path');
    }

    public function test_technician_updates_installation_location_only_for_own_orders(): void
    {
        Storage::fake('public');

        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        $technician = $this->createTechnician($tenant);
        $otherTechnician = $this->createTechnician($tenant);

        $order = ServiceOrder::factory()->forClient($client)->create([
            'created_by' => $admin->getKey(),
            'technician_id' => $technician->getKey(),
        ]);

        Sanctum::actingAs($technician);

        $this->putJson("/api/technician/service-orders/{$order->uuid}/installation-location", [
            'installation_location' => 'Sala de estar, parede frontal.',
            'installation_location_photo_path' => 'uploads/installation-location.jpg',
        ])
            ->assertOk()
            ->assertJsonPath('data.installation_location', 'Sala de estar, parede frontal.')
            ->assertJsonPath('data.installation_location_photo_path', 'uploads/installation-location.jpg');

        $this->assertDatabaseHas('service_orders', [
            'id' => $order->getKey(),
            'installation_location' => 'Sala de estar, parede frontal.',
            'installation_location_photo_path' => 'uploads/installation-location.jpg',
        ]);

        Sanctum::actingAs($otherTechnician);

        $this->putJson("/api/technician/service-orders/{$order->uuid}/installation-location", [
            'installation_location' => 'Tentativa de outro técnico.',
        ])->assertNotFound();
    }

    private function createTechnician($tenant): User
    {
        $technician = User::factory()->for($tenant)->technician()->create();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        return $technician;
    }
}
