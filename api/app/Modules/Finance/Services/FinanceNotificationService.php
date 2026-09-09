<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\FinanceNotificationChannel;
use App\Modules\Finance\Support\FinanceNotificationMessage;
use Illuminate\Support\Facades\Log;

class FinanceNotificationService
{
    /**
     * @param  iterable<FinanceNotificationChannel>  $channels
     */
    public function __construct(
        private readonly iterable $channels = [],
    ) {}

    public function notify(FinanceNotificationMessage $message): void
    {
        $sent = false;

        foreach ($this->channels as $channel) {
            try {
                $channel->send($message);
                $sent = true;
            } catch (\Throwable $exception) {
                Log::warning('finance.notification.channel_failed', [
                    'channel' => $channel::class,
                    'to' => $message->to,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if (! $sent) {
            Log::info('finance.notification.fallback', [
                'to' => $message->to,
                'subject' => $message->subject,
                'body' => $message->body,
            ]);
        }
    }
}
