<?php

namespace Tests\Feature\Notification;

use App\Modules\Notification\Models\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_user_registers_device_token(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createClient($tenant);

        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/devices', [
            'token' => 'ExponentPushToken[abc]',
            'platform' => 'android',
        ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'token' => 'ExponentPushToken[abc]',
            'platform' => 'android',
        ]);
    }

    public function test_registering_same_token_rebinds_to_current_user(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $first = $this->createClient($tenant);
        $second = $this->createClient($tenant);

        Sanctum::actingAs($first);
        $this->postJson('/api/notifications/devices', [
            'token' => 'ExponentPushToken[shared]',
            'platform' => 'ios',
        ])->assertCreated();

        Sanctum::actingAs($second);
        $this->postJson('/api/notifications/devices', [
            'token' => 'ExponentPushToken[shared]',
            'platform' => 'ios',
        ])->assertCreated();

        $this->assertSame(1, DeviceToken::query()
            ->withoutGlobalScopes()
            ->where('token', 'ExponentPushToken[shared]')
            ->count());

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'ExponentPushToken[shared]',
            'user_id' => $second->getKey(),
        ]);
    }

    public function test_user_removes_own_token_but_not_others(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $owner = $this->createClient($tenant);
        $other = $this->createClient($tenant);

        $token = new DeviceToken;
        $token->forceFill([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $owner->getKey(),
            'platform' => 'android',
            'token' => 'ExponentPushToken[owner]',
        ])->save();

        Sanctum::actingAs($other);
        $this->deleteJson("/api/notifications/devices/{$token->uuid}")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/notifications/devices/{$token->uuid}")->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['id' => $token->getKey()]);
    }
}
