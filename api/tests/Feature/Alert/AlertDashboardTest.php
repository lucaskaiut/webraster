<?php

namespace Tests\Feature\Alert;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\Alert;
use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AlertDashboardTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_dashboard_returns_aggregates(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();

        $this->createAlert($tenant, $client, $vehicle, AlertType::SOS, 'critical', 'open');
        $this->createAlert($tenant, $client, $vehicle, AlertType::SPEED, 'medium', 'resolved');

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/alerts/dashboard')
            ->assertOk()
            ->assertJsonPath('data.open_total', 1)
            ->assertJsonPath('data.critical_open_total', 1)
            ->assertJsonPath('data.by_severity.critical', 1)
            ->assertJsonPath('data.top_vehicles.0.vehicle_id', $vehicle->uuid)
            ->assertJsonPath('data.top_vehicles.0.total', 1)
            ->assertJsonPath('data.critical_open.0.type', 'sos');

        $this->assertSame(2, array_sum($response->json('data.by_day')));
    }

    public function test_client_user_only_sees_own_alerts_in_dashboard(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $vehicleA = Vehicle::factory()->forClient($clientA)->create();
        $vehicleB = Vehicle::factory()->forClient($clientB)->create();

        $this->createAlert($tenant, $clientA, $vehicleA, AlertType::SOS, 'critical', 'open');
        $this->createAlert($tenant, $clientB, $vehicleB, AlertType::SOS, 'critical', 'open');

        Sanctum::actingAs($this->createClient($tenant, ['client_id' => $clientA->getKey()]));

        $this->getJson('/api/alerts/dashboard')
            ->assertOk()
            ->assertJsonPath('data.open_total', 1)
            ->assertJsonPath('data.top_vehicles.0.vehicle_id', $vehicleA->uuid);
    }

    private function createAlert(
        Tenant $tenant,
        Client $client,
        Vehicle $vehicle,
        AlertType $type,
        string $severity,
        string $status,
    ): Alert {
        $alert = new Alert;
        $alert->forceFill([
            'tenant_id' => $tenant->getKey(),
            'client_id' => $client->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'type' => $type,
            'severity' => $severity,
            'status' => $status,
            'title' => $type->label(),
            'occurred_at' => now(),
        ])->save();

        return $alert;
    }
}
