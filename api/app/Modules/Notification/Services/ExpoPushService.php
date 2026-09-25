<?php

namespace App\Modules\Notification\Services;

use App\Modules\Alert\Models\UserNotification;
use App\Modules\Notification\Exceptions\ExpoPushException;
use App\Modules\Notification\Models\DeviceToken;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP do Expo Push Service (entrega via FCM/APNs).
 */
class ExpoPushService
{
    /**
     * Envia uma notificação para um token e devolve o id do ticket (recibo).
     */
    public function send(DeviceToken $token, UserNotification $notification): ?string
    {
        if (! config('notification.push.enabled')) {
            throw new RuntimeException('Push desabilitado (PUSH_ENABLED=false).');
        }

        $response = $this->request()->post(config('notification.push.url'), [
            'to' => $token->token,
            'title' => $notification->title,
            'body' => $notification->body ?? '',
            'sound' => 'default',
            'priority' => 'high',
            'data' => array_merge($notification->data ?? [], [
                'notification_id' => $notification->uuid,
            ]),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Expo push HTTP '.$response->status().': '.$response->body());
        }

        /** @var array<string, mixed>|null $ticket */
        $ticket = $response->json('data');

        if (! is_array($ticket)) {
            throw new RuntimeException('Resposta inválida do Expo push.');
        }

        if (($ticket['status'] ?? null) !== 'ok') {
            throw new ExpoPushException(
                (string) ($ticket['message'] ?? 'Falha no envio do Expo push.'),
                $ticket['details']['error'] ?? null,
            );
        }

        return isset($ticket['id']) ? (string) $ticket['id'] : null;
    }

    /**
     * Consulta os recibos de tickets enviados.
     *
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>>
     */
    public function receipts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $response = $this->request()->post(config('notification.push.receipts_url'), [
            'ids' => array_values($ids),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Expo receipts HTTP '.$response->status().': '.$response->body());
        }

        /** @var array<string, array<string, mixed>> $data */
        $data = $response->json('data') ?? [];

        return $data;
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout((int) config('notification.push.timeout', 15))
            ->acceptJson()
            ->asJson();

        $accessToken = config('notification.push.access_token');

        if (filled($accessToken)) {
            $request = $request->withToken((string) $accessToken);
        }

        return $request;
    }
}
