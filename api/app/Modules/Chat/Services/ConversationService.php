<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Enums\ConversationAiMode;
use App\Modules\Chat\Events\ChatConversationUpdated;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ConversationService
{
    public function paginate(int $perPage = 30, ?string $search = null, ?string $filter = null): LengthAwarePaginator
    {
        return Conversation::query()
            ->with(['contact', 'lead.stage', 'messages' => fn ($q) => $q->latest('sent_at')->limit(1)])
            ->when($search, function ($q) use ($search): void {
                $q->whereHas('contact', function ($contact) use ($search): void {
                    $contact->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filter === 'unread', fn ($q) => $q->where('unread_count', '>', 0))
            ->when($filter === 'ai', fn ($q) => $q->where('ai_mode', ConversationAiMode::AI_ACTIVE))
            ->when($filter === 'human', fn ($q) => $q->where('ai_mode', ConversationAiMode::HUMAN_HANDOFF))
            ->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    public function markRead(Conversation $conversation): Conversation
    {
        $conversation->forceFill(['unread_count' => 0])->save();

        ChatConversationUpdated::dispatch(TenantContext::tenant(), $conversation->fresh(['contact', 'lead.stage']));

        return $conversation;
    }

    public function setAiMode(Conversation $conversation, ConversationAiMode $mode): Conversation
    {
        $conversation->forceFill(['ai_mode' => $mode])->save();

        if ($conversation->lead) {
            $conversation->lead->forceFill([
                'ai_enabled' => $mode === ConversationAiMode::AI_ACTIVE,
            ])->save();
        }

        ChatConversationUpdated::dispatch(TenantContext::tenant(), $conversation->fresh(['contact', 'lead.stage']));

        return $conversation;
    }
}
