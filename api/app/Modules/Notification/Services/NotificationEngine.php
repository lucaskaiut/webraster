<?php

namespace App\Modules\Notification\Services;

use App\Modules\Alert\Models\UserNotification;
use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Enums\NotificationChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Jobs\SendPushNotificationJob;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\User\Models\User;
use Illuminate\Support\Collection;

/**
 * Motor central de notificações: registra a caixa de entrada (in-app) e
 * despacha os canais externos (hoje push; e-mail segue nos fluxos existentes).
 */
class NotificationEngine
{
    /**
     * @param  Collection<int, User>  $recipients
     * @return int quantidade de notificações criadas
     */
    public function send(Collection $recipients, NotificationMessage $message): int
    {
        $created = 0;

        foreach ($recipients->unique('id') as $user) {
            $notification = new UserNotification;
            $notification->forceFill([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->getKey(),
                'alert_id' => $message->alertId,
                'type' => $message->type,
                'source' => $message->source->value,
                'created_by' => $message->createdBy,
                'title' => $message->title,
                'body' => $message->body,
                'data' => $message->data,
            ])->save();

            if ($message->inApp) {
                $this->recordDelivery($notification, $user, NotificationChannel::IN_APP);
            }

            if ($message->push) {
                SendPushNotificationJob::dispatch($notification->getKey());
            }

            $created++;
        }

        return $created;
    }

    private function recordDelivery(
        UserNotification $notification,
        User $user,
        NotificationChannel $channel,
    ): void {
        $delivery = new NotificationDelivery;
        $delivery->forceFill([
            'tenant_id' => $user->tenant_id,
            'user_notification_id' => $notification->getKey(),
            'user_id' => $user->getKey(),
            'channel' => $channel,
            'status' => NotificationDeliveryStatus::SENT,
            'sent_at' => now(),
        ])->save();
    }
}
