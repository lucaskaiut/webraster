<?php

namespace Tests\Feature\Vehicle;

use App\Modules\Client\Models\Client;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class VehicleImageTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_adds_image_to_vehicle_and_exposes_it_on_show(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/vehicles/{$vehicle->uuid}/images", [
            'path' => 'uploads/foto-1.jpg',
        ])
            ->assertCreated()
            ->assertJsonPath('data.path', 'uploads/foto-1.jpg')
            ->assertJsonPath('data.url', asset('storage/uploads/foto-1.jpg'));

        $this->assertDatabaseHas('vehicle_images', [
            'vehicle_id' => $vehicle->getKey(),
            'path' => 'uploads/foto-1.jpg',
        ]);

        $this->getJson("/api/vehicles/{$vehicle->uuid}")
            ->assertOk()
            ->assertJsonPath('data.images.0.path', 'uploads/foto-1.jpg');
    }

    public function test_index_exposes_images_ordered_by_sort_order(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        VehicleImage::factory()->forVehicle($vehicle)->create([
            'path' => 'uploads/capa.jpg',
            'sort_order' => 1,
        ]);
        VehicleImage::factory()->forVehicle($vehicle)->create([
            'path' => 'uploads/detalhe.jpg',
            'sort_order' => 2,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/vehicles')
            ->assertOk()
            ->assertJsonPath('data.0.images.0.path', 'uploads/capa.jpg')
            ->assertJsonPath('data.0.images.1.path', 'uploads/detalhe.jpg');
    }

    public function test_removes_image_and_deletes_stored_file(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $image = VehicleImage::factory()->forVehicle($vehicle)->create([
            'path' => 'uploads/foto-2.jpg',
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('uploads/foto-2.jpg', 'content');

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->deleteJson("/api/vehicles/{$vehicle->uuid}/images/{$image->uuid}")->assertOk();

        $this->assertDatabaseMissing('vehicle_images', ['id' => $image->getKey()]);
        Storage::disk('public')->assertMissing('uploads/foto-2.jpg');
    }

    public function test_requires_path_to_add_image(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/vehicles/{$vehicle->uuid}/images", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');
    }
}
