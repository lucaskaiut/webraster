<?php

namespace Tests\Feature\Notification;

use App\Modules\Client\Models\Client;
use App\Modules\Notification\Jobs\SendPushNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ManualNotificationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_manual_send_requires_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createClient($tenant);

        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/send', [
            'title' => 'Aviso',
            'body' => 'Mensagem',
            'audience' => 'tenant',
        ])->assertForbidden();
    }

    public function test_manual_send_reaches_client_users_only(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $client = Client::factory()->for($tenant)->create();
        $first = $this->createClient($tenant, ['client_id' => $client->getKey()]);
        $second = $this->createClient($tenant, ['client_id' => $client->getKey()]);
        $outsider = $this->createClient($tenant);

        Queue::fake();
        Sanctum::actingAs($admin);

        $this->postJson('/api/notifications/send', [
            'title' => 'Aviso importante',
            'body' => 'Mensagem do painel',
            'audience' => 'client',
            'client_id' => $client->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.recipients', 2);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $first->getKey(),
            'source' => 'manual',
            'created_by' => $admin->getKey(),
            'title' => 'Aviso importante',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $second->getKey(),
            'source' => 'manual',
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $outsider->getKey(),
        ]);

        $this->assertDatabaseCount('notification_deliveries', 2);

        Queue::assertPushed(SendPushNotificationJob::class, 2);
    }

    public function test_manual_send_to_selected_users(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $target = $this->createClient($tenant);

        Queue::fake();
        Sanctum::actingAs($admin);

        $this->postJson('/api/notifications/send', [
            'title' => 'Só para você',
            'body' => 'Mensagem',
            'audience' => 'users',
            'user_ids' => [$target->uuid],
        ])
            ->assertCreated()
            ->assertJsonPath('data.recipients', 1);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $target->getKey(),
            'source' => 'manual',
        ]);
    }

    public function test_history_lists_deliveries_and_click_marks_notification(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $admin = $this->createAdmin($tenant);
        $recipient = $this->createClient($tenant);

        Queue::fake();
        Sanctum::actingAs($admin);

        $this->postJson('/api/notifications/send', [
            'title' => 'Com log',
            'body' => 'Mensagem',
            'audience' => 'users',
            'user_ids' => [$recipient->uuid],
        ])->assertCreated();

        $this->getJson('/api/notifications/sent?source=manual')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Com log')
            ->assertJsonPath('data.0.source', 'manual')
            ->assertJsonPath('data.0.user.id', $recipient->uuid)
            ->assertJsonPath('data.0.deliveries.0.channel', 'in_app')
            ->assertJsonPath('data.0.clicked_at', null);

        $notification = \App\Modules\Alert\Models\UserNotification::query()
            ->withoutGlobalScopes()
            ->where('user_id', $recipient->getKey())
            ->firstOrFail();

        Sanctum::actingAs($recipient);

        $this->postJson("/api/notifications/{$notification->uuid}/click")
            ->assertOk()
            ->assertJsonPath('data.clicked_at', fn ($value) => $value !== null);

        $this->assertNotNull($notification->fresh()->clicked_at);
    }
}
