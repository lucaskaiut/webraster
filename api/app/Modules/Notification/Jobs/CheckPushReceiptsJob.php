<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Enums\NotificationChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Models\DeviceToken;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Services\ExpoPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta os recibos dos pushes já aceitos pela Expo para confirmar entrega
 * (ou falha) e remover tokens de dispositivos desinstalados.
 */
class CheckPushReceiptsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ExpoPushService $push): void
    {
        NotificationDelivery::query()
            ->withoutGlobalScopes()
            ->where('channel', NotificationChannel::PUSH)
            ->where('status', NotificationDeliveryStatus::SENT)
            ->whereNotNull('provider_message_id')
            ->whereNull('receipt_checked_at')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('id')
            ->chunkById(300, function ($deliveries) use ($push): void {
                $ids = $deliveries->pluck('provider_message_id')->filter()->values()->all();

                if ($ids === []) {
                    return;
                }

                try {
                    $receipts = $push->receipts($ids);
                } catch (Throwable $exception) {
                    Log::warning('notification.receipts_failed', [
                        'message' => $exception->getMessage(),
                    ]);

                    return;
                }

                foreach ($deliveries as $delivery) {
                    $receipt = $receipts[$delivery->provider_message_id] ?? null;

                    if (! is_array($receipt)) {
                        continue;
                    }

                    $ok = ($receipt['status'] ?? null) === 'ok';

                    $delivery->forceFill([
                        'status' => $ok
                            ? NotificationDeliveryStatus::DELIVERED
                            : NotificationDeliveryStatus::FAILED,
                        'delivered_at' => $ok ? now() : null,
                        'receipt_checked_at' => now(),
                        'error' => $ok
                            ? null
                            : (string) ($receipt['message'] ?? 'Falha no recibo do Expo push.'),
                    ])->save();

                    if (! $ok && ($receipt['details']['error'] ?? null) === 'DeviceNotRegistered') {
                        DeviceToken::query()
                            ->withoutGlobalScopes()
                            ->whereKey($delivery->device_token_id)
                            ->delete();
                    }
                }
            });
    }
}
