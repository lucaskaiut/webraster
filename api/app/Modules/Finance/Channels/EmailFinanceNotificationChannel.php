<?php

namespace App\Modules\Finance\Channels;

use App\Modules\Finance\Contracts\FinanceNotificationChannel;
use App\Modules\Finance\Support\FinanceNotificationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailFinanceNotificationChannel implements FinanceNotificationChannel
{
    public function send(FinanceNotificationMessage $message): void
    {
        if (blank($message->to)) {
            return;
        }

        try {
            Mail::raw($message->body, function ($mail) use ($message): void {
                $mail->to($message->to)->subject($message->subject);
            });
        } catch (\Throwable $exception) {
            Log::info('finance.notification.email_fallback', [
                'to' => $message->to,
                'subject' => $message->subject,
                'body' => $message->body,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
