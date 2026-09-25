<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Alert\Models\UserNotification;
use App\Modules\Notification\Enums\NotificationChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Exceptions\ExpoPushException;
use App\Modules\Notification\Models\DeviceToken;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Services\ExpoPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia uma notificação da caixa de entrada para todos os dispositivos do
 * usuário e registra uma entrega por token (log + recibos).
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $userNotificationId) {}

    public function handle(ExpoPushService $push): void
    {
        $notification = UserNotification::query()
            ->withoutGlobalScopes()
            ->find($this->userNotificationId);

        if ($notification === null) {
            return;
        }

        $tokens = DeviceToken::query()
            ->withoutGlobalScopes()
            ->where('user_id', $notification->user_id)
            ->get();

        foreach ($tokens as $token) {
            $this->sendToToken($push, $notification, $token);
        }
    }

    private function sendToToken(
        ExpoPushService $push,
        UserNotification $notification,
        DeviceToken $token,
    ): void {
        $delivery = new NotificationDelivery;
        $delivery->forceFill([
            'tenant_id' => $notification->tenant_id,
            'user_notification_id' => $notification->getKey(),
            'user_id' => $notification->user_id,
            'device_token_id' => $token->getKey(),
            'channel' => NotificationChannel::PUSH,
            'status' => NotificationDeliveryStatus::SENT,
            'provider' => 'expo',
        ])->save();

        try {
            $ticketId = $push->send($token, $notification);

            $delivery->forceFill([
                'provider_message_id' => $ticketId,
                'sent_at' => now(),
                'error' => null,
            ])->save();

            $token->forceFill(['last_used_at' => now()])->save();
        } catch (ExpoPushException $exception) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::FAILED,
                'error' => $exception->getMessage(),
            ])->save();

            if ($exception->isDeviceNotRegistered()) {
                $token->delete();
            }

            Log::warning('notification.push_failed', [
                'notification_id' => $notification->getKey(),
                'device_token_id' => $token->getKey(),
                'error' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::FAILED,
                'error' => $exception->getMessage(),
            ])->save();

            Log::warning('notification.push_failed', [
                'notification_id' => $notification->getKey(),
                'device_token_id' => $token->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
