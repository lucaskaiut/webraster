<?php

namespace App\Modules\Chat\Contracts;

use App\Modules\Chat\DTOs\NormalizedInboundMessage;
use App\Modules\Chat\DTOs\OutboundMessageResult;
use App\Modules\Chat\Models\MessagingConnection;
use Illuminate\Http\Request;

interface MessagingGatewayInterface
{
    public function key(): string;

    public function label(): string;

    /**
     * @return list<string>
     */
    public function capabilities(): array;

    /**
     * @return list<array{name: string, label: string, type: string, required?: bool, secret?: bool, hint?: string}>
     */
    public function credentialSchema(): array;

    public function sendText(MessagingConnection $connection, string $phone, string $text): OutboundMessageResult;

    public function markAsRead(MessagingConnection $connection, string $externalConversationId, string $externalMessageId): void;

    public function authenticateWebhook(MessagingConnection $connection, Request $request): bool;

    /**
     * @return list<NormalizedInboundMessage>
     */
    public function normalizeWebhook(MessagingConnection $connection, Request $request): array;
}
