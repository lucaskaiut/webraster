<?php

namespace Tests\Feature\Equipment;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class EquipmentTraccarSyncTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_store_creates_device_in_traccar(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();

        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/api/devices')) {
                return Http::response([]);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/api/devices')) {
                return Http::response([
                    'id' => 77,
                    'uniqueId' => $request['uniqueId'],
                    'name' => $request['name'],
                    'status' => 'unknown',
                ]);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/equipments', [
            'imei' => '71311525',
            'model' => 'Celular Lucas',
        ])
            ->assertCreated()
            ->assertJsonPath('data.imei', '71311525');

        $this->assertDatabaseHas('equipments', [
            'tenant_id' => $tenant->getKey(),
            'imei' => '71311525',
            'traccar_device_id' => 77,
        ]);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/api/devices')
            && $request['uniqueId'] === '71311525'
            && $request['name'] === 'Celular Lucas');
    }

    public function test_store_links_existing_traccar_device_by_imei(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();

        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/api/devices')) {
                return Http::response([
                    [
                        'id' => 1,
                        'uniqueId' => '71311525',
                        'name' => 'Lucas',
                        'status' => 'unknown',
                    ],
                ]);
            }

            return Http::response(['error' => 'should not create'], 500);
        });

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/equipments', [
            'imei' => '71311525',
            'model' => 'FMB920',
        ])->assertCreated();

        $this->assertDatabaseHas('equipments', [
            'imei' => '71311525',
            'traccar_device_id' => 1,
        ]);

        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_update_imei_updates_traccar_device(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();

        $equipment = Equipment::factory()->forTenant($tenant)->create([
            'imei' => '11111111',
            'traccar_device_id' => 9,
            'model' => 'GT06',
        ]);

        Http::fake(function ($request) {
            if ($request->method() === 'PUT' && str_contains($request->url(), '/api/devices/9')) {
                return Http::response([
                    'id' => 9,
                    'uniqueId' => $request['uniqueId'],
                    'name' => $request['name'],
                ]);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/equipments/{$equipment->uuid}", [
            'imei' => '22222222',
        ])->assertOk();

        $this->assertDatabaseHas('equipments', [
            'id' => $equipment->getKey(),
            'imei' => '22222222',
            'traccar_device_id' => 9,
        ]);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request['uniqueId'] === '22222222');
    }

    public function test_store_creates_equipment_even_when_traccar_fails(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();
        Log::spy();

        Http::fake(fn () => Http::response(['error' => 'traccar offline'], 500));

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/equipments', [
            'imei' => '71311526',
            'model' => 'FMB920',
        ])
            ->assertCreated()
            ->assertJsonPath('data.imei', '71311526');

        $this->assertDatabaseHas('equipments', [
            'tenant_id' => $tenant->getKey(),
            'imei' => '71311526',
            'traccar_device_id' => null,
        ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'traccar.device_sync_failed'
                && ($context['imei'] ?? null) === '71311526')
            ->once();
    }

    public function test_store_creates_equipment_when_traccar_is_unreachable(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();

        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/equipments', [
            'imei' => '71311527',
            'model' => 'FMB920',
        ])
            ->assertCreated()
            ->assertJsonPath('data.imei', '71311527');

        $this->assertDatabaseHas('equipments', [
            'tenant_id' => $tenant->getKey(),
            'imei' => '71311527',
            'traccar_device_id' => null,
        ]);
    }

    public function test_update_keeps_changes_when_traccar_fails(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();

        $equipment = Equipment::factory()->forTenant($tenant)->create([
            'imei' => '11111111',
            'traccar_device_id' => 9,
            'model' => 'GT06',
        ]);

        Http::fake(fn () => Http::response(['error' => 'traccar offline'], 500));

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/equipments/{$equipment->uuid}", [
            'imei' => '22222222',
        ])
            ->assertOk()
            ->assertJsonPath('data.imei', '22222222');

        $this->assertDatabaseHas('equipments', [
            'id' => $equipment->getKey(),
            'imei' => '22222222',
            'traccar_device_id' => 9,
        ]);
    }

    private function enableTraccarGateway(): void
    {
        config([
            'traccar.enabled' => true,
            'traccar.base_url' => 'http://traccar.test',
            'traccar.token' => 'test-token',
            'traccar.timeout' => 5,
        ]);

        $this->app->instance(TraccarGateway::class, $this->app->make(HttpTraccarGateway::class));
    }
}
