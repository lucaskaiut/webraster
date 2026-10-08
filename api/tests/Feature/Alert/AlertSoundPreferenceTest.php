<?php

namespace Tests\Feature\Alert;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertSoundPreference;
use App\Modules\Client\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AlertSoundPreferenceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_client_lists_enabled_alerts_with_default_sound_and_catalog(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/alert-sound-preferences')
            ->assertOk()
            ->assertJsonPath('data.sounds', ['default', 'chime', 'alert', 'siren'])
            ->assertJsonPath('data.alerts.0.sound', 'default');

        $this->assertSame(
            array_map(fn (AlertType $type) => $type->value, AlertType::clientConfigurable()),
            collect($response->json('data.alerts'))->pluck('type')->all(),
        );
    }

    public function test_silenced_alerts_are_hidden_from_the_sound_list(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $this->putJson('/api/alert-configs/portal/ignition_on', ['is_enabled' => false])
            ->assertOk();

        $response = $this->getJson('/api/alert-sound-preferences')->assertOk();

        $this->assertNotContains(
            'ignition_on',
            collect($response->json('data.alerts'))->pluck('type')->all(),
        );
    }

    public function test_user_can_choose_a_sound_per_alert_type(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $this->putJson('/api/alert-sound-preferences/sos', ['sound' => 'siren'])
            ->assertOk()
            ->assertJsonPath('data.type', 'sos')
            ->assertJsonPath('data.sound', 'siren');

        $this->assertDatabaseHas('alert_sound_preferences', [
            'user_id' => $user->getKey(),
            'type' => 'sos',
            'sound' => 'siren',
        ]);

        $response = $this->getJson('/api/alert-sound-preferences')->assertOk();
        $sos = collect($response->json('data.alerts'))->firstWhere('type', 'sos');

        $this->assertSame('siren', $sos['sound']);

        // Troca de som atualiza a mesma linha (sem duplicar).
        $this->putJson('/api/alert-sound-preferences/sos', ['sound' => 'chime'])
            ->assertOk()
            ->assertJsonPath('data.sound', 'chime');

        $this->assertSame(1, AlertSoundPreference::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->where('type', 'sos')
            ->count());
    }

    public function test_sound_preference_is_per_user(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $userA = $this->createClient($tenant, ['client_id' => $client->getKey()]);
        $userB = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($userA);
        $this->putJson('/api/alert-sound-preferences/battery', ['sound' => 'chime'])->assertOk();

        Sanctum::actingAs($userB);
        $response = $this->getJson('/api/alert-sound-preferences')->assertOk();
        $battery = collect($response->json('data.alerts'))->firstWhere('type', 'battery');

        $this->assertSame('default', $battery['sound']);
        $this->assertDatabaseMissing('alert_sound_preferences', [
            'user_id' => $userB->getKey(),
            'type' => 'battery',
        ]);
    }

    public function test_rejects_unknown_sound_and_type(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $this->putJson('/api/alert-sound-preferences/sos', ['sound' => 'explosion'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sound']);

        $this->putJson('/api/alert-sound-preferences/speed', ['sound' => 'siren'])
            ->assertNotFound();
    }

    public function test_staff_can_configure_sounds_for_their_own_notifications(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/alert-sound-preferences')->assertOk();

        $this->assertCount(count(AlertType::clientConfigurable()), $response->json('data.alerts'));

        $this->putJson('/api/alert-sound-preferences/offline', ['sound' => 'alert'])
            ->assertOk();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/alert-sound-preferences')->assertUnauthorized();
    }
}
