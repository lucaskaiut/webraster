<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\DTOs\NormalizedInboundMessage;
use App\Modules\Chat\Enums\ChatType;
use App\Modules\Chat\Enums\ConversationAiMode;
use App\Modules\Chat\Enums\MessageDirection;
use App\Modules\Chat\Enums\MessageStatus;
use App\Modules\Chat\Events\ChatMessageCreated;
use App\Modules\Chat\Events\ChatConversationUpdated;
use App\Modules\Chat\Jobs\ProcessCrmAiReplyJob;
use App\Modules\Chat\Models\Contact;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Chat\Models\Message;
use App\Modules\Crm\Services\LeadAutoCreateService;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Support\Facades\DB;

final class InboundMessageProcessor
{
    public function __construct(
        private readonly LeadAutoCreateService $leadAutoCreate,
    ) {}

    public function process(MessagingConnection $connection, NormalizedInboundMessage $inbound): ?Message
    {
        if ($inbound->chatType === ChatType::GROUP) {
            return null;
        }

        TenantContext::set($connection->tenant);

        return DB::transaction(function () use ($connection, $inbound): ?Message {
            $existing = Message::query()
                ->where('conversation_id', '>', 0)
                ->whereHas('conversation', fn ($q) => $q->where('connection_id', $connection->getKey()))
                ->where('external_id', $inbound->externalMessageId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $contact = $this->upsertContact($connection, $inbound);
            $conversation = $this->upsertConversation($connection, $contact, $inbound);

            if ($inbound->direction === MessageDirection::INBOUND && $conversation->lead_id === null) {
                $lead = $this->leadAutoCreate->createFromInbound($contact, $connection);
                $conversation->forceFill(['lead_id' => $lead->getKey()])->save();
            }

            $sentAt = $inbound->timestamp !== null
                ? now()->setTimestamp($inbound->timestamp)
                : now();

            $message = Message::query()->create([
                'tenant_id' => $connection->tenant_id,
                'conversation_id' => $conversation->getKey(),
                'external_id' => $inbound->externalMessageId,
                'direction' => $inbound->direction,
                'sender_type' => $inbound->senderType,
                'message_type' => $inbound->messageType,
                'text' => $inbound->text,
                'status' => MessageStatus::DELIVERED,
                'sent_at' => $sentAt,
                'metadata' => ['raw' => $inbound->rawPayload],
            ]);

            $this->touchConversation($conversation, $inbound, $message);

            if ($conversation->lead) {
                $conversation->lead->forceFill(['last_interaction_at' => now()])->save();
            }

            $connection->loadMissing('tenant');
            ChatMessageCreated::dispatch($connection->tenant, $message);
            ChatConversationUpdated::dispatch($connection->tenant, $conversation->fresh(['contact', 'lead.stage']));

            if ($inbound->direction === MessageDirection::INBOUND && $conversation->isAiEligible()) {
                ProcessCrmAiReplyJob::dispatch($conversation->getKey());
            }

            return $message;
        });
    }

    private function upsertContact(MessagingConnection $connection, NormalizedInboundMessage $inbound): Contact
    {
        $phone = $inbound->contactPhone;

        $contact = Contact::query()
            ->where('tenant_id', $connection->tenant_id)
            ->when($phone, fn ($q) => $q->where('phone', $phone))
            ->first();

        if ($contact === null) {
            $contact = Contact::query()->create([
                'tenant_id' => $connection->tenant_id,
                'phone' => $phone,
                'name' => $inbound->contactName,
                'external_id' => $inbound->externalConversationId,
            ]);
        } else {
            $contact->fill([
                'name' => $contact->name ?: $inbound->contactName,
                'external_id' => $contact->external_id ?: $inbound->externalConversationId,
            ])->save();
        }

        return $contact;
    }

    private function upsertConversation(
        MessagingConnection $connection,
        Contact $contact,
        NormalizedInboundMessage $inbound,
    ): Conversation {
        $conversation = Conversation::query()->firstOrCreate(
            [
                'connection_id' => $connection->getKey(),
                'external_conversation_id' => $inbound->externalConversationId,
            ],
            [
                'tenant_id' => $connection->tenant_id,
                'contact_id' => $contact->getKey(),
                'chat_type' => $inbound->chatType,
                'status' => 'open',
                'ai_mode' => ConversationAiMode::AI_ACTIVE,
            ],
        );

        return $conversation;
    }

    private function touchConversation(
        Conversation $conversation,
        NormalizedInboundMessage $inbound,
        Message $message,
    ): void {
        $updates = [
            'last_message_at' => $message->sent_at ?? now(),
        ];

        if ($inbound->direction === MessageDirection::INBOUND) {
            $updates['last_inbound_message_at'] = $message->sent_at ?? now();
            $updates['unread_count'] = $conversation->unread_count + 1;
        } else {
            $updates['last_outbound_message_at'] = $message->sent_at ?? now();
        }

        $conversation->forceFill($updates)->save();
    }
}
