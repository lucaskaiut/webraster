<?php

namespace App\Modules\Chat\DTOs;

use App\Modules\Chat\Enums\ChatType;
use App\Modules\Chat\Enums\MessageDirection;
use App\Modules\Chat\Enums\MessageType;
use App\Modules\Chat\Enums\SenderType;

final readonly class NormalizedInboundMessage
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $externalMessageId,
        public string $externalConversationId,
        public ChatType $chatType,
        public MessageDirection $direction,
        public SenderType $senderType,
        public MessageType $messageType,
        public ?string $text,
        public ?string $contactPhone,
        public ?string $contactName,
        public ?int $timestamp,
        public array $rawPayload,
    ) {}
}
