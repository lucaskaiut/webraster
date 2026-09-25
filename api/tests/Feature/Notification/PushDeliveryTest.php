<?php

namespace Tests\Feature\Notification;

use App\Modules\Alert\Models\UserNotification;
use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationSource;
use App\Modules\Notification\Jobs\CheckPushReceiptsJob;
use App\Modules\Notification\Jobs\SendPushNotificationJob;
use App\Modules\Notification\Models\DeviceToken;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Services\ExpoPushService;
use App\Modules\Notification\Services\NotificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PushDeliveryTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_engine_creates_in_app_notification_delivery_and_push_job(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createClient($tenant);

        Queue::fake();

        $created = app(NotificationEngine::class)->send(
            collect([$user]),
            new NotificationMessage(
                type: 'manual',
                title: 'Olá',
                body: 'Mundo',
                source: NotificationSource::MANUAL,
            ),
        );

        $this->assertSame(1, $created);

        $notification = UserNotification::query()->withoutGlobalScopes()->firstOrFail();

        $this->assertDatabaseHas('notification_deliveries', [
            'user_notification_id' => $notification->getKey(),
            'user_id' => $user->getKey(),
            'channel' => 'in_app',
            'status' => 'sent',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_push_job_sends_to_all_user_devices(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createClient($tenant);

        config(['notification.push.enabled' => true]);

        Http::fake([
            'exp.host/*' => Http::response(['data' => ['status' => 'ok', 'id' => 'ticket-1']], 200),
        ]);

        $notification = $this->notificationFor($user, push: false);
        $token = $this->deviceTokenFor($tenant->getKey(), $user->getKey(), 'ExponentPushToken[one]');

        (new SendPushNotificationJob($notification->getKey()))
            ->handle(app(ExpoPushService::class));

        $this->assertDatabaseHas('notification_deliveries', [
            'user_notification_id' => $notification->getKey(),
            'device_token_id' => $token->getKey(),
            'channel' => 'push',
            'status' => 'sent',
            'provider_message_id' => 'ticket-1',
        ]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'push/send')
                && $request['to'] === 'ExponentPushToken[one]'
                && $request['data']['notification_id'] !== null;
        });

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_push_job_removes_token_when_device_not_registered(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createClient($tenant);

        config(['notification.push.enabled' => true]);

        Http::fake([
            'exp.host/*' => Http::response([
                'data' => [
                    'status' => 'error',
                    'message' => 'Device is not registered',
                    'details' => ['error' => 'DeviceNotRegistered'],
                ],
            ], 200),
        ]);

        $notification = $this->notificationFor($user, push: false);
        $token = $this->deviceTokenFor($tenant->getKey(), $user->getKey(), 'ExponentPushToken[dead]');

        (new SendPushNotificationJob($notification->getKey()))
            ->handle(app(ExpoPushService::class));

        $this->assertDatabaseHas('notification_deliveries', [
            'user_notification_id' => $notification->getKey(),
            'channel' => 'push',
            'status' => 'failed',
        ]);

        $this->assertDatabaseMissing('device_tokens', ['id' => $token->getKey()]);
    }

    public function test_receipts_job_marks_delivery_as_delivered(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createClient($tenant);

        config(['notification.push.enabled' => true]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'getReceipts')) {
                return Http::response(['data' => ['ticket-9' => ['status' => 'ok']]], 200);
            }

            return Http::response(['data' => ['status' => 'ok', 'id' => 'ticket-9']], 200);
        });

        $notification = $this->notificationFor($user, push: false);
        $token = $this->deviceTokenFor($tenant->getKey(), $user->getKey(), 'ExponentPushToken[receipt]');

        $delivery = new NotificationDelivery;
        $delivery->forceFill([
            'tenant_id' => $tenant->getKey(),
            'user_notification_id' => $notification->getKey(),
            'user_id' => $user->getKey(),
            'device_token_id' => $token->getKey(),
            'channel' => 'push',
            'status' => NotificationDeliveryStatus::SENT,
            'provider' => 'expo',
            'provider_message_id' => 'ticket-9',
            'sent_at' => now(),
        ])->save();

        (new CheckPushReceiptsJob)->handle(app(ExpoPushService::class));

        $delivery->refresh();

        $this->assertSame(NotificationDeliveryStatus::DELIVERED, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($delivery->receipt_checked_at);
    }

    public function test_receipts_job_removes_token_when_not_registered(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createClient($tenant);

        config(['notification.push.enabled' => true]);

        Http::fake([
            'exp.host/*' => Http::response([
                'data' => [
                    'ticket-10' => [
                        'status' => 'error',
                        'message' => 'Device is not registered',
                        'details' => ['error' => 'DeviceNotRegistered'],
                    ],
                ],
            ], 200),
        ]);

        $notification = $this->notificationFor($user, push: false);
        $token = $this->deviceTokenFor($tenant->getKey(), $user->getKey(), 'ExponentPushToken[gone]');

        $delivery = new NotificationDelivery;
        $delivery->forceFill([
            'tenant_id' => $tenant->getKey(),
            'user_notification_id' => $notification->getKey(),
            'user_id' => $user->getKey(),
            'device_token_id' => $token->getKey(),
            'channel' => 'push',
            'status' => NotificationDeliveryStatus::SENT,
            'provider' => 'expo',
            'provider_message_id' => 'ticket-10',
            'sent_at' => now(),
        ])->save();

        (new CheckPushReceiptsJob)->handle(app(ExpoPushService::class));

        $this->assertSame(NotificationDeliveryStatus::FAILED, $delivery->fresh()->status);
        $this->assertDatabaseMissing('device_tokens', ['id' => $token->getKey()]);
    }

    private function notificationFor($user, bool $push): UserNotification
    {
        app(NotificationEngine::class)->send(
            collect([$user]),
            new NotificationMessage(
                type: 'manual',
                title: 'Push',
                body: 'Corpo',
                source: NotificationSource::MANUAL,
                push: $push,
            ),
        );

        return UserNotification::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->firstOrFail();
    }

    private function deviceTokenFor(int $tenantId, int $userId, string $token): DeviceToken
    {
        $deviceToken = new DeviceToken;
        $deviceToken->forceFill([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'platform' => 'android',
            'token' => $token,
        ])->save();

        return $deviceToken;
    }
}
