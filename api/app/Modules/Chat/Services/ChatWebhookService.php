<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Enums\WebhookEventStatus;
use App\Modules\Chat\Jobs\ProcessMessagingWebhookJob;
use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Chat\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ChatWebhookService
{
    public function ingest(MessagingConnection $connection, Request $request): WebhookEvent
    {
        $payload = $request->all();
        $event = $this->store($connection, $payload);

        if (! $event->status->isTerminal() && $event->dispatched_at === null) {
            ProcessMessagingWebhookJob::dispatch($event->getKey());
            $event->markDispatched();
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(MessagingConnection $connection, array $payload): WebhookEvent
    {
        $dedupeKey = $this->dedupeKey($connection, $payload);

        try {
            return WebhookEvent::query()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'connection_id' => $connection->getKey(),
                    'event_type' => (string) ($payload['event'] ?? ''),
                    'status' => WebhookEventStatus::PENDING,
                    'payload' => $payload,
                    'received_at' => now(),
                ],
            );
        } catch (QueryException $exception) {
            $existing = WebhookEvent::query()->where('dedupe_key', $dedupeKey)->first();

            if ($existing !== null) {
                return $existing;
            }

            Log::warning('chat.webhook_store_failed', [
                'connection_id' => $connection->getKey(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dedupeKey(MessagingConnection $connection, array $payload): string
    {
        $event = (string) ($payload['event'] ?? '');
        $data = $payload['data'] ?? [];
        $items = is_array($data) && array_is_list($data) ? $data : [is_array($data) ? $data : []];

        $parts = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = data_get($item, 'key.id');
            $jid = data_get($item, 'key.remoteJid');
            $ts = data_get($item, 'messageTimestamp');

            if ($id !== null) {
                $parts[] = implode('|', [
                    (string) $connection->getKey(),
                    $event,
                    (string) $jid,
                    (string) $id,
                    (string) $ts,
                ]);
            }
        }

        if ($parts === []) {
            $parts[] = hash('sha256', json_encode($payload) ?: '');
        }

        return hash('sha256', implode('::', $parts));
    }
}
