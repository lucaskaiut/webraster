<?php

namespace App\Modules\Chat\Gateways;

use App\Modules\Chat\Contracts\MessagingGatewayInterface;
use App\Modules\Chat\DTOs\NormalizedInboundMessage;
use App\Modules\Chat\DTOs\OutboundMessageResult;
use App\Modules\Chat\Enums\ChatType;
use App\Modules\Chat\Enums\MessageDirection;
use App\Modules\Chat\Enums\MessageType;
use App\Modules\Chat\Enums\SenderType;
use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Chat\Support\EvolutionConfig;
use App\Modules\Chat\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapter Evolution API v2.3.x — único ponto com payloads HTTP da Evolution.
 */
final class EvolutionGateway implements MessagingGatewayInterface
{
    public function key(): string
    {
        return 'evolution';
    }

    public function label(): string
    {
        return 'Evolution API';
    }

    public function capabilities(): array
    {
        return [
            'SEND_TEXT',
            'SEND_MEDIA',
            'READ_RECEIPTS',
            'WEBHOOKS',
        ];
    }

    public function credentialSchema(): array
    {
        return [
            [
                'name' => 'webhook_secret',
                'label' => 'Segredo do webhook (opcional)',
                'type' => 'password',
                'required' => false,
                'secret' => true,
                'hint' => 'Se configurado, valide o header apikey ou token do webhook.',
            ],
        ];
    }

    public function sendText(MessagingConnection $connection, string $phone, string $text): OutboundMessageResult
    {
        $instance = (string) $connection->instance_name;
        $number = PhoneNumber::toE164Digits($phone);

        if ($instance === '' || $number === '') {
            return new OutboundMessageResult(false, error: 'Instância ou número inválido.');
        }

        $url = $this->baseUrl($connection)."/message/sendText/{$instance}";

        $response = Http::withHeaders($this->headers($connection))
            ->timeout(30)
            ->post($url, [
                'number' => $number,
                'text' => $text,
            ]);

        Log::info('chat.evolution.send_text', [
            'connection_id' => $connection->getKey(),
            'status' => $response->status(),
        ]);

        if (! $response->successful()) {
            return new OutboundMessageResult(
                false,
                error: $response->json('message') ?? $response->body(),
                raw: $response->json(),
            );
        }

        $json = $response->json();
        $externalId = data_get($json, 'key.id')
            ?? data_get($json, 'message.key.id')
            ?? data_get($json, 'data.key.id');

        return new OutboundMessageResult(true, is_string($externalId) ? $externalId : null, raw: is_array($json) ? $json : null);
    }

    public function markAsRead(MessagingConnection $connection, string $externalConversationId, string $externalMessageId): void
    {
        $instance = (string) $connection->instance_name;

        if ($instance === '') {
            return;
        }

        $url = $this->baseUrl($connection)."/chat/markMessageAsRead/{$instance}";

        Http::withHeaders($this->headers($connection))
            ->timeout(15)
            ->post($url, [
                'readMessages' => [
                    [
                        'remoteJid' => $externalConversationId,
                        'fromMe' => false,
                        'id' => $externalMessageId,
                    ],
                ],
            ]);
    }

    public function authenticateWebhook(MessagingConnection $connection, Request $request): bool
    {
        $secret = (string) $connection->credential('webhook_secret', '');

        if ($secret === '') {
            return true;
        }

        $apiKey = $request->header('apikey') ?? $request->header('Authorization');

        if (is_string($apiKey) && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        return is_string($apiKey) && hash_equals($secret, $apiKey);
    }

    public function normalizeWebhook(MessagingConnection $connection, Request $request): array
    {
        $payload = $request->all();
        $event = strtolower((string) ($payload['event'] ?? ''));

        if (! in_array($event, ['messages.upsert', 'messages_upsert'], true)) {
            return [];
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        $messages = array_is_list($data) ? $data : [$data];
        $normalized = [];

        foreach ($messages as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalizedMessage = $this->normalizeMessageItem($item, $payload);

            if ($normalizedMessage !== null) {
                $normalized[] = $normalizedMessage;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $rootPayload
     */
    private function normalizeMessageItem(array $item, array $rootPayload): ?NormalizedInboundMessage
    {
        $key = $item['key'] ?? null;

        if (! is_array($key)) {
            return null;
        }

        $remoteJid = (string) ($key['remoteJid'] ?? '');

        if ($remoteJid === '') {
            return null;
        }

        $fromMe = (bool) ($key['fromMe'] ?? false);
        $messageId = (string) ($key['id'] ?? '');

        if ($messageId === '') {
            return null;
        }

        $chatType = str_contains($remoteJid, '@g.us') ? ChatType::GROUP : ChatType::INDIVIDUAL;

        $text = $this->extractText($item);
        $messageType = $this->resolveMessageType($item, $text);

        $direction = $fromMe ? MessageDirection::OUTBOUND : MessageDirection::INBOUND;
        $senderType = $fromMe ? SenderType::SYSTEM : SenderType::CONTACT;

        $phone = PhoneNumber::fromRemoteJid($remoteJid);
        $pushName = isset($item['pushName']) ? (string) $item['pushName'] : null;
        $timestamp = isset($item['messageTimestamp']) ? (int) $item['messageTimestamp'] : null;

        return new NormalizedInboundMessage(
            externalMessageId: $messageId,
            externalConversationId: $remoteJid,
            chatType: $chatType,
            direction: $direction,
            senderType: $senderType,
            messageType: $messageType,
            text: $text,
            contactPhone: $phone,
            contactName: $pushName,
            timestamp: $timestamp,
            rawPayload: ['item' => $item, 'root' => $rootPayload],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractText(array $item): ?string
    {
        $message = $item['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        if (isset($message['conversation']) && is_string($message['conversation'])) {
            return $message['conversation'];
        }

        $extended = $message['extendedTextMessage']['text'] ?? null;

        if (is_string($extended)) {
            return $extended;
        }

        $caption = $message['imageMessage']['caption']
            ?? $message['videoMessage']['caption']
            ?? $message['documentMessage']['caption']
            ?? null;

        return is_string($caption) ? $caption : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveMessageType(array $item, ?string $text): MessageType
    {
        $message = $item['message'] ?? [];

        if (! is_array($message)) {
            return MessageType::UNKNOWN;
        }

        if (isset($message['imageMessage'])) {
            return MessageType::IMAGE;
        }

        if (isset($message['audioMessage'])) {
            return MessageType::AUDIO;
        }

        if (isset($message['videoMessage'])) {
            return MessageType::VIDEO;
        }

        if (isset($message['documentMessage'])) {
            return MessageType::DOCUMENT;
        }

        if ($text !== null) {
            return MessageType::TEXT;
        }

        $type = strtolower((string) ($item['messageType'] ?? ''));

        return match ($type) {
            'conversation', 'extendedtextmessage' => MessageType::TEXT,
            default => MessageType::UNKNOWN,
        };
    }

    public function createWhatsappInstance(
        MessagingConnection $connection,
        string $instanceName,
        string $webhookUrl,
    ): void {
        $url = $this->baseUrl($connection).'/instance/create';

        $response = Http::withHeaders($this->headers($connection))
            ->timeout(60)
            ->post($url, [
                'instanceName' => $instanceName,
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => false,
                'webhook' => [
                    'enabled' => true,
                    'url' => $webhookUrl,
                    'webhookByEvents' => false,
                    'events' => [
                        'MESSAGES_UPSERT',
                        'MESSAGES_UPDATE',
                        'CONNECTION_UPDATE',
                    ],
                ],
            ]);

        if ($response->successful() || $response->status() === 409) {
            return;
        }

        $rawBody = strtolower($response->body());
        if (str_contains($rawBody, 'already') || str_contains($rawBody, 'exist')) {
            return;
        }

        $message = $response->json('message') ?? $response->json('error') ?? $response->body();

        if (is_array($message)) {
            $message = json_encode($message) ?: 'Falha ao criar instância.';
        }

        throw new RuntimeException(is_string($message) ? $message : 'Falha ao criar instância na Evolution.');
    }

    /**
     * @return array<string, mixed>
     */
    public function connectWhatsapp(MessagingConnection $connection): array
    {
        $instance = (string) $connection->instance_name;

        if ($instance === '') {
            throw new RuntimeException('Instância não configurada.');
        }

        $url = $this->baseUrl($connection)."/instance/connect/{$instance}";

        $response = Http::withHeaders($this->headers($connection))
            ->timeout(30)
            ->get($url);

        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->body();

            throw new RuntimeException(is_string($message) ? $message : 'Falha ao obter QR Code.');
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchConnectionState(MessagingConnection $connection): array
    {
        $instance = (string) $connection->instance_name;

        if ($instance === '') {
            throw new RuntimeException('Instância não configurada.');
        }

        $url = $this->baseUrl($connection)."/instance/connectionState/{$instance}";

        $response = Http::withHeaders($this->headers($connection))
            ->timeout(15)
            ->get($url);

        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->body();

            throw new RuntimeException(is_string($message) ? $message : 'Falha ao consultar status.');
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @return array<string, string>
     */
    private function headers(MessagingConnection $connection): array
    {
        $apiKey = (string) ($connection->credential('api_key') ?: EvolutionConfig::apiKey());

        return [
            'apikey' => $apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function baseUrl(MessagingConnection $connection): string
    {
        $url = trim((string) $connection->base_url);

        if ($url === '') {
            $url = EvolutionConfig::baseUrl();
        }

        return rtrim($url, '/');
    }
}
