<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceOrderFrustrationAndAttachmentLimitTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_technician_frustrates_order_with_reason(): void
    {
        [$order, $technician, $admin] = $this->orderForTechnician();
        Sanctum::actingAs($technician);

        $this->patchJson("/api/technician/service-orders/{$order->uuid}/status", [
            'status' => 'frustrated',
        ])->assertUnprocessable()->assertJsonValidationErrors('frustration_reason');

        $this->patchJson("/api/technician/service-orders/{$order->uuid}/status", [
            'status' => 'frustrated',
            'frustration_reason' => 'Cliente ausente no local.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'frustrated')
            ->assertJsonPath('data.status_label', 'Frustrada')
            ->assertJsonPath('data.frustration_reason', 'Cliente ausente no local.');

        $order->refresh();
        $this->assertSame((int) $technician->getKey(), (int) $order->frustrated_by);
        $this->assertNotNull($order->frustrated_at);

        $this->assertDatabaseHas('user_notifications', [
            'type' => 'service_order.frustrated',
            'user_id' => $admin->getKey(),
        ]);

        // Frustrada é terminal.
        $this->patchJson("/api/technician/service-orders/{$order->uuid}/status", [
            'status' => 'in_progress',
        ])->assertUnprocessable();

        $this->getJson('/api/technician/service-orders?scope=history')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_technician_can_frustrate_after_starting(): void
    {
        [$order, $technician] = $this->orderForTechnician(ServiceOrderStatus::IN_PROGRESS);
        Sanctum::actingAs($technician);

        $this->patchJson("/api/technician/service-orders/{$order->uuid}/status", [
            'status' => 'frustrated',
            'frustration_reason' => 'Veículo sem bateria.',
        ])->assertOk()->assertJsonPath('data.status', 'frustrated');
    }

    public function test_technician_cancels_order_with_reason(): void
    {
        [$order, $technician] = $this->orderForTechnician();
        Sanctum::actingAs($technician);

        $this->patchJson("/api/technician/service-orders/{$order->uuid}/status", [
            'status' => 'cancelled',
        ])->assertUnprocessable()->assertJsonValidationErrors('cancellation_reason');

        $this->patchJson("/api/technician/service-orders/{$order->uuid}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente cancelou por telefone.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Cliente cancelou por telefone.');
    }

    public function test_panel_frustrates_and_kanban_has_frustrated_column(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $order = ServiceOrder::factory()->forClient($client)->create(['created_by' => $admin->getKey()]);

        $this->patchJson("/api/service-orders/{$order->uuid}/status", [
            'status' => 'frustrated',
            'frustration_reason' => 'Endereço não encontrado.',
        ])->assertOk()->assertJsonPath('data.status', 'frustrated');

        $this->getJson('/api/service-orders/kanban')
            ->assertOk()
            ->assertJsonCount(1, 'data.frustrated');

        $this->putJson("/api/service-orders/{$order->uuid}", ['notes' => 'x'])->assertUnprocessable();
    }

    public function test_open_order_accepts_only_one_attachment(): void
    {
        Storage::fake('local');
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $this->post('/api/service-orders', [
            'address' => 'Rua das Flores, 100 - Curitiba',
            'attachments' => [
                UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments');

        $id = $this->post('/api/service-orders', [
            'address' => 'Rua das Flores, 100 - Curitiba',
            'attachments' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $this->post("/api/service-orders/{$id}/attachments", [
            'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_attachments_are_released_after_technician_starts(): void
    {
        Storage::fake('local');
        [$order, $technician] = $this->orderForTechnician();
        Sanctum::actingAs($technician);

        $this->post("/api/technician/service-orders/{$order->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post("/api/technician/service-orders/{$order->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $order->forceFill(['status' => ServiceOrderStatus::IN_PROGRESS])->save();

        foreach (['b.pdf', 'c.pdf'] as $name) {
            $this->post("/api/technician/service-orders/{$order->uuid}/attachments", [
                'file' => UploadedFile::fake()->create($name, 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->assertSame(3, $order->attachments()->count());
    }

    public function test_stores_address_reference(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        Sanctum::actingAs($admin);

        $link = 'https://maps.app.goo.gl/abc123';

        $id = $this->postJson('/api/service-orders', [
            'address' => 'Rua das Flores, 100 - Curitiba',
            'address_reference' => "  {$link}  ",
        ])
            ->assertCreated()
            ->assertJsonPath('data.address_reference', $link)
            ->json('data.id');

        $this->putJson("/api/service-orders/{$id}", ['address_reference' => 'Portão azul'])
            ->assertOk()
            ->assertJsonPath('data.address_reference', 'Portão azul');

        $this->putJson("/api/service-orders/{$id}", ['address_reference' => ''])
            ->assertOk()
            ->assertJsonPath('data.address_reference', null);
    }

    /**
     * @return array{0: ServiceOrder, 1: User, 2: User}
     */
    private function orderForTechnician(ServiceOrderStatus $status = ServiceOrderStatus::OPEN): array
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $admin = $this->createAdmin($tenant);
        $technician = User::factory()->for($tenant)->technician()->create();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        $order = ServiceOrder::factory()->forClient($client)->create([
            'status' => $status,
            'technician_id' => $technician->getKey(),
            'created_by' => $admin->getKey(),
        ]);

        return [$order, $technician, $admin];
    }
}
