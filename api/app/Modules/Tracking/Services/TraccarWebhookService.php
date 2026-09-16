<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Enums\TraccarEventStatus;
use App\Modules\Tracking\Jobs\ProcessTraccarEvent;
use App\Modules\Tracking\Models\TraccarEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Ingestão durável do forward do Traccar: grava o payload bruto na inbox e
 * enfileira o processamento (posição, geocercas e alertas). Nunca processa
 * de forma síncrona — se a gravação falhar, exceção sobe e o Traccar reenvia.
 */
class TraccarWebhookService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(array $payload): TraccarEvent
    {
        $event = $this->store($payload);

        if (! $event->status->isTerminal() && $event->dispatched_at === null) {
            ProcessTraccarEvent::dispatch($event->getKey());

            $event->markDispatched();
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(array $payload): TraccarEvent
    {
        $dedupeKey = $this->dedupeKey($payload);

        try {
            return TraccarEvent::query()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'traccar_device_id' => data_get($payload, 'position.deviceId')
                        ?? data_get($payload, 'device.id'),
                    'status' => TraccarEventStatus::PENDING,
                    'payload' => $payload,
                    'received_at' => now(),
                ],
            );
        } catch (QueryException $exception) {
            $existing = TraccarEvent::query()->where('dedupe_key', $dedupeKey)->first();

            if ($existing !== null) {
                return $existing;
            }

            Log::warning('traccar.webhook_store_failed', [
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dedupeKey(array $payload): string
    {
        $position = $payload['position'] ?? null;

        if (is_array($position) && filled($position['deviceTime'] ?? null)) {
            return hash('sha256', sprintf(
                'position|%s|%s',
                (string) ($position['deviceId'] ?? ''),
                (string) $position['deviceTime'],
            ));
        }

        return hash('sha256', 'payload|'.(json_encode($payload) ?: ''));
    }
}
