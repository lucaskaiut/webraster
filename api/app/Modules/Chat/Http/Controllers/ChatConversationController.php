<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Enums\ConversationAiMode;
use App\Modules\Chat\Http\Resources\ChatConversationResource;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatConversationController extends ApiController
{
    public function __construct(private readonly ConversationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $paginator = $this->service->paginate(
            (int) $request->integer('per_page', 30),
            $request->string('search')->toString() ?: null,
            $request->string('filter')->toString() ?: null,
        );

        return $this->paginated(ChatConversationResource::collection($paginator));
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->success(
            ChatConversationResource::make($conversation->load(['contact', 'lead.stage', 'assignedUser'])),
        );
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->orderByDesc('sent_at')
            ->paginate((int) $request->integer('per_page', 50));

        return $this->paginated(ChatMessageResource::collection($messages));
    }

    public function markRead(Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        return $this->success(
            ChatConversationResource::make($this->service->markRead($conversation)->load(['contact', 'lead.stage'])),
        );
    }

    public function aiMode(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $request->validate([
            'mode' => ['required', 'in:ai_active,ai_disabled,human_handoff'],
        ]);

        $mode = ConversationAiMode::from($request->string('mode')->toString());

        return $this->success(
            ChatConversationResource::make(
                $this->service->setAiMode($conversation, $mode)->load(['contact', 'lead.stage']),
            ),
        );
    }
}
