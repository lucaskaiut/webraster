<?php

namespace Tests\Feature\Crm;

use App\Modules\Chat\Jobs\ProcessMessagingWebhookJob;
use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Chat\Models\Message;
use App\Modules\Crm\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CrmWebhookIdempotencyTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_duplicate_webhook_creates_single_message_and_lead(): void
    {
        [, $tenant] = $this->createOperationalChild();

        $connection = MessagingConnection::query()->create([
            'tenant_id' => $tenant->getKey(),
            'provider' => 'evolution',
            'name' => 'WhatsApp',
            'base_url' => 'https://evolution.example.com',
            'credentials' => ['api_key' => 'test'],
            'instance_name' => 'demo',
            'is_active' => true,
        ]);

        $payload = [
            'event' => 'messages.upsert',
            'instance' => 'demo',
            'data' => [
                'key' => [
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'MSG123',
                ],
                'pushName' => 'João',
                'message' => ['conversation' => 'Olá'],
                'messageTimestamp' => 1_700_000_000,
            ],
        ];

        Queue::fake();

        $this->postJson("/api/webhooks/messaging/evolution/{$connection->uuid}", $payload)->assertOk();
        $this->postJson("/api/webhooks/messaging/evolution/{$connection->uuid}", $payload)->assertOk();

        Queue::assertPushed(ProcessMessagingWebhookJob::class, 2);

        $job = new ProcessMessagingWebhookJob(1);
        // process manually with real job after first ingest
        $eventId = \App\Modules\Chat\Models\WebhookEvent::query()->value('id');
        (new ProcessMessagingWebhookJob((int) $eventId))->handle(
            app(\App\Modules\Chat\Services\InboundMessageProcessor::class),
            app(\App\Modules\Chat\Support\MessagingGatewayResolver::class),
        );

        (new ProcessMessagingWebhookJob((int) $eventId))->handle(
            app(\App\Modules\Chat\Services\InboundMessageProcessor::class),
            app(\App\Modules\Chat\Support\MessagingGatewayResolver::class),
        );

        $this->assertSame(1, Message::query()->count());
        $this->assertSame(1, Lead::query()->count());
    }
}
