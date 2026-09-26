<?php

namespace Tests\Feature\Alert;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Client\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AlertConfigPortalTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_client_sees_all_configurable_alerts_enabled_by_default(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/alert-configs/portal')
            ->assertOk()
            ->assertJsonPath('data.0.is_enabled', true);

        $this->assertSame(
            array_map(fn (AlertType $type) => $type->value, AlertType::clientConfigurable()),
            collect($response->json('data'))->pluck('type')->all(),
        );
    }

    public function test_client_can_silence_and_resume_alert_without_changing_channels(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $this->putJson('/api/alert-configs/portal/ignition_on', ['is_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.type', 'ignition_on')
            ->assertJsonPath('data.is_enabled', false);

        $this->assertDatabaseHas('alert_configs', [
            'client_id' => $client->getKey(),
            'type' => 'ignition_on',
            'is_enabled' => false,
            'notify_in_app' => true,
            'notify_push' => true,
            'notify_email' => true,
        ]);

        $this->putJson('/api/alert-configs/portal/ignition_on', ['is_enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true);

        $this->assertDatabaseHas('alert_configs', [
            'client_id' => $client->getKey(),
            'type' => 'ignition_on',
            'is_enabled' => true,
            'notify_in_app' => true,
            'notify_push' => true,
            'notify_email' => true,
        ]);
    }

    public function test_portal_rejects_alerts_outside_the_client_subset(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $this->putJson('/api/alert-configs/portal/speed', ['is_enabled' => true])
            ->assertNotFound();
    }

    public function test_staff_cannot_use_the_client_portal(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/alert-configs/portal')->assertForbidden();
        $this->putJson('/api/alert-configs/portal/sos', ['is_enabled' => true])
            ->assertForbidden();
    }

    public function test_tenant_views_client_configs_and_client_is_forbidden(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);
        $this->putJson('/api/alert-configs/portal/sos', ['is_enabled' => true])->assertOk();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson("/api/alert-configs/client/{$client->uuid}")
            ->assertOk()
            ->assertJsonPath('data.2.type', 'sos')
            ->assertJsonPath('data.2.is_enabled', true);

        Sanctum::actingAs($user);
        $this->getJson("/api/alert-configs/client/{$client->uuid}")->assertForbidden();
    }

    public function test_client_toggle_does_not_affect_other_clients(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $clientA = Client::factory()->for($tenant)->create();
        $clientB = Client::factory()->for($tenant)->create();
        $userA = $this->createClient($tenant, ['client_id' => $clientA->getKey()]);

        Sanctum::actingAs($userA);
        $this->putJson('/api/alert-configs/portal/battery', ['is_enabled' => false])->assertOk();

        $this->assertDatabaseHas('alert_configs', [
            'client_id' => $clientA->getKey(),
            'type' => 'battery',
            'is_enabled' => false,
        ]);

        $this->assertDatabaseHas('alert_configs', [
            'client_id' => $clientB->getKey(),
            'type' => 'battery',
            'is_enabled' => true,
        ]);
    }
}
