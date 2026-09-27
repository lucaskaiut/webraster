<?php

namespace App\Modules\Crm\Services;

use App\Modules\Chat\Enums\ConversationAiMode;
use App\Modules\Chat\Enums\SenderType;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Chat\Services\OutboundMessageService;
use App\Modules\Crm\Support\LeadNotesAppender;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Log;

final class CrmAiHandoffService
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly OutboundMessageService $outbound,
        private readonly LeadNotesAppender $noteAppender,
    ) {}

    public function escalate(
        Conversation $conversation,
        string $reason,
        ?string $messageToCustomer = null,
        string $trigger = 'ai',
    ): void {
        $conversation->load(['lead', 'contact']);

        if ($conversation->ai_mode !== ConversationAiMode::HUMAN_HANDOFF) {
            $this->conversations->setAiMode($conversation, ConversationAiMode::HUMAN_HANDOFF);
        }

        $lead = $conversation->lead;

        if ($lead !== null) {
            $note = '[Handoff '.trim($trigger).'] '.trim($reason);
            $append = $this->noteAppender->append($lead->notes, $note);

            if ($append['appended']) {
                $lead->forceFill([
                    'notes' => $append['notes'],
                    'last_interaction_at' => now(),
                ])->save();
            }
        }

        $message = trim($messageToCustomer ?? '');

        if ($message === '') {
            $message = trim((string) config('crm.ai.handoff_default_customer_message', ''));
        }

        if ($message === '') {
            return;
        }

        $systemUser = User::query()->where('tenant_id', $conversation->tenant_id)->first();

        if ($systemUser === null) {
            Log::warning('crm.ai.handoff_no_user', ['conversation_id' => $conversation->getKey()]);

            return;
        }

        $this->outbound->sendText($conversation, $systemUser, $message, SenderType::AI);
    }
}
