<?php

namespace Tests\Feature\DeviceCommand;

use App\Modules\Client\Models\Client;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\Gateways\HttpTraccarGateway;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DeviceCommandTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_lists_command_types_from_traccar_without_protocol_mapping(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();
        $equipment = $this->makeLinkedEquipment($tenant, traccarId: 42);

        Http::fake([
            'http://traccar.test/api/commands/types*' => Http::response([
                ['type' => 'engineStop'],
                ['type' => 'engineResume'],
                ['type' => 'custom'],
                ['type' => 'rebootDevice'],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson("/api/devices/{$equipment->uuid}/commands")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'engineStop')
            ->assertJsonPath('data.3.type', 'rebootDevice');

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/commands/types')
            && $request['deviceId'] == 42);
    }

    public function test_caches_command_types_for_five_minutes(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();
        $equipment = $this->makeLinkedEquipment($tenant, traccarId: 42);
        Cache::flush();

        Http::fake([
            'http://traccar.test/api/commands/types*' => Http::response([
                ['type' => 'engineStop'],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson("/api/devices/{$equipment->uuid}/commands")->assertOk();
        $this->getJson("/api/devices/{$equipment->uuid}/commands")->assertOk();

        Http::assertSentCount(1);
    }

    public function test_sends_supported_command_and_audits_log(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();
        $equipment = $this->makeLinkedEquipment($tenant, traccarId: 42);
        $admin = $this->createAdmin($tenant);

        Http::fake([
            'http://traccar.test/api/commands/types*' => Http::response([
                ['type' => 'engineStop'],
                ['type' => 'custom'],
            ]),
            'http://traccar.test/api/commands/send' => Http::response([
                'id' => 99,
                'deviceId' => 42,
                'type' => 'engineStop',
            ]),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/devices/{$equipment->uuid}/commands", [
            'type' => 'engineStop',
            'attributes' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.command_type', 'engineStop')
            ->assertJsonPath('data.status', 'success');

        $this->assertDatabaseHas('device_command_logs', [
            'equipment_id' => $equipment->getKey(),
            'user_id' => $admin->getKey(),
            'command_type' => 'engineStop',
            'status' => DeviceCommandStatus::SUCCESS->value,
            'traccar_device_id' => 42,
        ]);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/api/commands/send')
            && $request['type'] === 'engineStop'
            && $request['deviceId'] == 42);
    }

    public function test_rejects_unsupported_command_with_422(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();
        $equipment = $this->makeLinkedEquipment($tenant, traccarId: 42);

        Http::fake([
            'http://traccar.test/api/commands/types*' => Http::response([
                ['type' => 'engineStop'],
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/devices/{$equipment->uuid}/commands", [
            'type' => 'alarmArm',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'Command not supported by device.');

        $this->assertSame(0, DeviceCommandLog::query()->withoutGlobalScopes()->count());
    }

    public function test_sends_custom_command_with_data_attribute(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->enableTraccarGateway();
        $equipment = $this->makeLinkedEquipment($tenant, traccarId: 42);

        Http::fake([
            'http://traccar.test/api/commands/types*' => Http::response([
                ['type' => 'custom'],
            ]),
            'http://traccar.test/api/commands/send' => Http::response([
                'id' => 1,
                'type' => 'custom',
            ]),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson("/api/devices/{$equipment->uuid}/commands", [
            'type' => 'custom',
            'attributes' => ['data' => 'RELAY,1#'],
        ])->assertOk();

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/api/commands/send')) {
                return false;
            }

            if (($request['type'] ?? null) !== 'custom') {
                return false;
            }

            $attributes = $request['attributes'] ?? null;

            if (is_array($attributes)) {
                return ($attributes['data'] ?? null) === 'RELAY,1#';
            }

            return is_object($attributes) && ($attributes->data ?? null) === 'RELAY,1#';
        });
    }

    public function test_forbids_send_without_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $equipment = $this->makeLinkedEquipment($tenant, traccarId: 42);

        Sanctum::actingAs($this->createClient($tenant));

        $this->getJson("/api/devices/{$equipment->uuid}/commands")->assertForbidden();
        $this->postJson("/api/devices/{$equipment->uuid}/commands", [
            'type' => 'engineStop',
        ])->assertForbidden();
    }

    private function makeLinkedEquipment($tenant, int $traccarId): Equipment
    {
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        return Equipment::factory()->assignedTo($vehicle)->create([
            'traccar_device_id' => $traccarId,
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
