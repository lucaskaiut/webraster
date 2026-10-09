<?php

namespace Tests\Feature\Alert;

use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Models\AlertSoundPreference;
use App\Modules\Alert\Models\UserNotification;
use App\Modules\Alert\Services\AlertSoundService;
use App\Modules\Client\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AlertSoundPreferenceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_lists_the_three_app_groups_with_default_sound_and_catalog(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/alert-sound-preferences')
            ->assertOk()
            ->assertJsonPath('data.sounds', ['default', 'chime', 'alert', 'siren'])
            ->assertJsonPath('data.alerts.0.type', AlertSoundService::GROUP_IGNITION)
            ->assertJsonPath('data.alerts.0.sound', 'default')
            ->assertJsonPath('data.alerts.1.type', AlertSoundService::GROUP_GEOFENCE)
            ->assertJsonPath('data.alerts.2.type', AlertSoundService::GROUP_POWERCUT);

        $this->assertSame(
            AlertSoundService::groupKeys(),
            collect($response->json('data.alerts'))->pluck('type')->all(),
        );
    }

    public function test_groups_stay_listed_even_when_the_client_silences_alerts(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $this->putJson('/api/alert-configs/portal/ignition_on', ['is_enabled' => false])
            ->assertOk();

        $response = $this->getJson('/api/alert-sound-preferences')->assertOk();

        $this->assertSame(
            AlertSoundService::groupKeys(),
            collect($response->json('data.alerts'))->pluck('type')->all(),
        );
    }

    public function test_user_can_choose_a_sound_per_group(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $this->putJson('/api/alert-sound-preferences/geofence', ['sound' => 'siren'])
            ->assertOk()
            ->assertJsonPath('data.type', 'geofence')
            ->assertJsonPath('data.sound', 'siren');

        $this->assertDatabaseHas('alert_sound_preferences', [
            'user_id' => $user->getKey(),
            'type' => 'geofence',
            'sound' => 'siren',
        ]);

        $response = $this->getJson('/api/alert-sound-preferences')->assertOk();
        $geofence = collect($response->json('data.alerts'))->firstWhere('type', 'geofence');

        $this->assertSame('siren', $geofence['sound']);

        // Troca de som atualiza a mesma linha (sem duplicar).
        $this->putJson('/api/alert-sound-preferences/geofence', ['sound' => 'chime'])
            ->assertOk()
            ->assertJsonPath('data.sound', 'chime');

        $this->assertSame(1, AlertSoundPreference::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->where('type', 'geofence')
            ->count());
    }

    public function test_sound_preference_is_per_user(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $userA = $this->createClient($tenant, ['client_id' => $client->getKey()]);
        $userB = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($userA);
        $this->putJson('/api/alert-sound-preferences/ignition', ['sound' => 'chime'])->assertOk();

        Sanctum::actingAs($userB);
        $response = $this->getJson('/api/alert-sound-preferences')->assertOk();
        $ignition = collect($response->json('data.alerts'))->firstWhere('type', 'ignition');

        $this->assertSame('default', $ignition['sound']);
        $this->assertDatabaseMissing('alert_sound_preferences', [
            'user_id' => $userB->getKey(),
            'type' => 'ignition',
        ]);
    }

    public function test_rejects_unknown_sound_and_group(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        Sanctum::actingAs($user);

        $this->putJson('/api/alert-sound-preferences/ignition', ['sound' => 'explosion'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sound']);

        // Tipos fora dos grupos do app não são mais configuráveis.
        $this->putJson('/api/alert-sound-preferences/sos', ['sound' => 'siren'])
            ->assertNotFound();

        $this->putJson('/api/alert-sound-preferences/speed', ['sound' => 'siren'])
            ->assertNotFound();
    }

    public function test_staff_can_configure_sounds_for_their_own_notifications(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->getJson('/api/alert-sound-preferences')->assertOk();

        $this->assertCount(count(AlertSoundService::groupKeys()), $response->json('data.alerts'));

        $this->putJson('/api/alert-sound-preferences/powercut', ['sound' => 'alert'])
            ->assertOk();
    }

    public function test_ignition_group_sound_applies_to_ignition_notifications(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);
        $sounds = app(AlertSoundService::class);

        $sounds->set($user, AlertSoundService::GROUP_IGNITION, 'chime');

        $notification = $this->userNotification($tenant->getKey(), $user->getKey(), 'ignition_off');

        $this->assertSame('chime', $sounds->soundFor($notification));
    }

    public function test_powercut_group_sound_applies_only_to_powercut_device_alarms(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);
        $sounds = app(AlertSoundService::class);

        $sounds->set($user, AlertSoundService::GROUP_POWERCUT, 'siren');

        $powerCut = $this->deviceAlarm($tenant->getKey(), 'powercut');
        $tow = $this->deviceAlarm($tenant->getKey(), 'tow');

        $this->assertSame('siren', $sounds->soundFor(
            $this->userNotification($tenant->getKey(), $user->getKey(), 'device_alarm', $powerCut->getKey()),
        ));
        $this->assertSame('default', $sounds->soundFor(
            $this->userNotification($tenant->getKey(), $user->getKey(), 'device_alarm', $tow->getKey()),
        ));
    }

    public function test_legacy_type_preference_is_used_when_the_group_has_no_choice(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);
        $sounds = app(AlertSoundService::class);

        AlertSoundPreference::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'type' => 'device_alarm',
            'sound' => 'alert',
        ]);

        $powerCut = $this->deviceAlarm($tenant->getKey(), 'powercut');

        $this->assertSame('alert', $sounds->soundFor(
            $this->userNotification($tenant->getKey(), $user->getKey(), 'device_alarm', $powerCut->getKey()),
        ));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/alert-sound-preferences')->assertUnauthorized();
    }

    private function userNotification(
        int $tenantId,
        int $userId,
        string $type,
        ?int $alertId = null,
    ): UserNotification {
        return UserNotification::query()->forceCreate([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'alert_id' => $alertId,
            'type' => $type,
            'source' => 'alert',
            'title' => 'Teste',
            'data' => [],
        ]);
    }

    private function deviceAlarm(int $tenantId, string $alarmCode): Alert
    {
        return Alert::query()->forceCreate([
            'tenant_id' => $tenantId,
            'type' => 'device_alarm',
            'severity' => 'high',
            'status' => 'open',
            'title' => 'Alarme do dispositivo',
            'meta' => ['alarm_code' => $alarmCode],
            'occurred_at' => now(),
        ]);
    }
}
