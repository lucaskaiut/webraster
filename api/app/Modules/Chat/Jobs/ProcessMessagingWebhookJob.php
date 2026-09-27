<?php

namespace App\Modules\Chat\Jobs;

use App\Modules\Chat\Enums\WebhookEventStatus;
use App\Modules\Chat\Models\WebhookEvent;
use App\Modules\Chat\Services\InboundMessageProcessor;
use App\Modules\Chat\Support\MessagingGatewayResolver;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMessagingWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $webhookEventId)
    {
        $this->onQueue((string) config('chat.queue', 'chat'));
    }

    public function handle(
        InboundMessageProcessor $processor,
        MessagingGatewayResolver $gateways,
    ): void {
        $event = WebhookEvent::query()->with('connection.tenant')->find($this->webhookEventId);

        if ($event === null) {
            return;
        }

        if ($event->status === WebhookEventStatus::PROCESSED) {
            return;
        }

        $connection = $event->connection;
        TenantContext::set($connection->tenant);

        $event->forceFill(['status' => WebhookEventStatus::PROCESSING])->save();

        try {
            $gateway = $gateways->resolve($connection->provider);
            $request = Request::create('/', 'POST', is_array($event->payload) ? $event->payload : []);

            foreach ($gateway->normalizeWebhook($connection, $request) as $normalized) {
                $processor->process($connection, $normalized);
            }

            $event->forceFill([
                'status' => WebhookEventStatus::PROCESSED,
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::error('chat.webhook_process_failed', [
                'event_id' => $event->getKey(),
                'message' => $exception->getMessage(),
            ]);

            $event->forceFill([
                'status' => WebhookEventStatus::FAILED,
                'error' => $exception->getMessage(),
                'processed_at' => now(),
            ])->save();

            throw $exception;
        }
    }
}
