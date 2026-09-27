<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Enums\MessageDirection;
use App\Modules\Chat\Enums\MessageStatus;
use App\Modules\Chat\Enums\MessageType;
use App\Modules\Chat\Enums\SenderType;
use App\Modules\Chat\Events\ChatMessageCreated;
use App\Modules\Chat\Events\ChatConversationUpdated;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Support\MessagingGatewayResolver;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OutboundMessageService
{
    public function __construct(
        private readonly MessagingGatewayResolver $gateways,
    ) {}

    public function sendText(Conversation $conversation, User $user, string $text, SenderType $senderType = SenderType::USER): Message
    {
        $connection = $conversation->connection()->firstOrFail();
        $contact = $conversation->contact()->firstOrFail();
        $phone = (string) $contact->phone;

        if ($phone === '') {
            throw new RuntimeException('Contato sem telefone.');
        }

        $gateway = $this->gateways->resolve($connection->provider);

        return DB::transaction(function () use ($conversation, $user, $text, $senderType, $connection, $phone, $gateway): Message {
            $message = Message::query()->create([
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => $conversation->getKey(),
                'direction' => MessageDirection::OUTBOUND,
                'sender_type' => $senderType,
                'sender_user_id' => $senderType === SenderType::USER ? $user->getKey() : null,
                'message_type' => MessageType::TEXT,
                'text' => $text,
                'status' => MessageStatus::PENDING,
                'sent_at' => now(),
            ]);

            $result = $gateway->sendText($connection, $phone, $text);

            if (! $result->success) {
                $message->forceFill([
                    'status' => MessageStatus::FAILED,
                    'metadata' => ['error' => $result->error, 'raw' => $result->raw],
                ])->save();

                throw new RuntimeException($result->error ?? 'Falha ao enviar mensagem.');
            }

            $message->forceFill([
                'status' => MessageStatus::SENT,
                'external_id' => $result->externalId,
                'metadata' => ['raw' => $result->raw],
            ])->save();

            $conversation->forceFill([
                'last_message_at' => now(),
                'last_outbound_message_at' => now(),
            ])->save();

            $connection->loadMissing('tenant');
            ChatMessageCreated::dispatch($connection->tenant, $message);
            ChatConversationUpdated::dispatch($connection->tenant, $conversation->fresh(['contact', 'lead.stage']));

            return $message;
        });
    }
}
