<?php

namespace Tests\Feature\Alert;

use App\Modules\Client\Models\Client;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class VehicleAlertDefaultsTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultsPayload(): array
    {
        return [
            [
                'type' => 'speed',
                'alarm_code' => null,
                'is_enabled' => true,
                'notify_in_app' => false,
                'notify_monitoring' => false,
                'notify_push' => false,
                'notify_email' => true,
            ],
            [
                'type' => 'sos',
                'alarm_code' => null,
                'is_enabled' => false,
                'notify_in_app' => false,
                'notify_monitoring' => true,
                'notify_push' => false,
                'notify_email' => false,
            ],
            [
                'type' => 'device_alarm',
                'alarm_code' => 'TOW',
                'is_enabled' => true,
                'notify_in_app' => false,
                'notify_monitoring' => false,
                'notify_push' => true,
                'notify_email' => false,
            ],
        ];
    }

    public function test_update_persists_and_normalizes_vehicle_alert_defaults(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', ['vehicle_alert_defaults' => $this->defaultsPayload()])
            ->assertOk()
            ->assertJsonPath('data.vehicle_alert_defaults.0.type', 'speed')
            ->assertJsonPath('data.vehicle_alert_defaults.0.notify_email', true)
            ->assertJsonPath('data.vehicle_alert_defaults.2.alarm_code', 'tow');
    }

    public function test_update_rejects_types_outside_the_configurable_subset(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', [
            'vehicle_alert_defaults' => [
                [
                    'type' => 'online',
                    'is_enabled' => true,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_alert_defaults.0.type']);
    }

    public function test_defaults_endpoint_falls_back_to_system_defaults(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $data = collect($this->getJson('/api/alert-configs/defaults')->assertOk()->json('data'));

        $speed = $data->firstWhere('type', 'speed');
        $sos = $data->firstWhere('type', 'sos');

        $this->assertFalse($speed['is_enabled']);
        $this->assertFalse($speed['notify_email']);
        $this->assertTrue($sos['is_enabled']);
        $this->assertTrue($sos['notify_email']);
    }

    public function test_defaults_endpoint_returns_configured_values(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', ['vehicle_alert_defaults' => $this->defaultsPayload()])->assertOk();

        $data = collect($this->getJson('/api/alert-configs/defaults')->assertOk()->json('data'));

        $speed = $data->firstWhere('type', 'speed');
        $sos = $data->firstWhere('type', 'sos');
        $tow = $data->firstWhere('alarm_code', 'tow');

        $this->assertSame('Excesso de velocidade', $speed['label']);
        $this->assertTrue($speed['is_enabled']);
        $this->assertFalse($speed['notify_in_app']);
        $this->assertFalse($sos['is_enabled']);
        $this->assertTrue($sos['notify_monitoring']);
        $this->assertTrue($tow['is_enabled']);
        $this->assertTrue($tow['notify_push']);
    }

    public function test_new_vehicle_starts_with_tenant_defaults(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant', ['vehicle_alert_defaults' => $this->defaultsPayload()])->assertOk();

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'DEF1T23',
        ])->assertCreated();

        $vehicle = Vehicle::query()->where('plate', 'DEF1T23')->firstOrFail();

        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'speed',
            'is_enabled' => true,
            'notify_in_app' => false,
            'notify_monitoring' => false,
            'notify_push' => false,
            'notify_email' => true,
        ]);

        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'sos',
            'is_enabled' => false,
            'notify_monitoring' => true,
        ]);

        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'device_alarm',
            'alarm_code' => 'tow',
            'is_enabled' => true,
            'notify_in_app' => false,
            'notify_push' => true,
        ]);
    }

    public function test_tenant_defaults_do_not_override_existing_vehicle_configs(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/vehicles', [
            'client_id' => $client->uuid,
            'plate' => 'OLD1T23',
        ])->assertCreated();

        $vehicle = Vehicle::query()->where('plate', 'OLD1T23')->firstOrFail();

        // Padrão do sistema: excesso de velocidade desabilitado.
        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'speed',
            'is_enabled' => false,
        ]);

        $this->putJson('/api/tenant', ['vehicle_alert_defaults' => $this->defaultsPayload()])->assertOk();

        // Os padrões da empresa não alteram veículos já cadastrados.
        $this->assertDatabaseHas('alert_configs', [
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'speed',
            'is_enabled' => false,
        ]);
    }
}
