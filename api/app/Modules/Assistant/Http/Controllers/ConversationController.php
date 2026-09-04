<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Http\Requests\StoreConversationRequest;
use App\Modules\Assistant\Http\Requests\UpdateConversationRequest;
use App\Modules\Assistant\Http\Resources\ConversationResource;
use App\Modules\Assistant\Http\Resources\ConversationSummaryResource;
use App\Modules\Assistant\Models\Conversation;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $conversations = Conversation::query()
            ->where('user_id', $request->user()->getKey())
            ->with('latestMessage')
            ->withCount('messages')
            ->when(filled($search = $request->string('search')->toString() ?: null), fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated(ConversationSummaryResource::collection($conversations));
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $this->authorize('create', Conversation::class);

        $conversation = Conversation::query()->create([
            'user_id' => $request->user()->getKey(),
            'title' => $request->validated('title') ?? 'Nova conversa',
        ]);

        return $this->created(
            ConversationResource::make($conversation->load('messages')),
            'Conversa criada com sucesso.',
        );
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);

        return $this->success(ConversationResource::make($conversation));
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation->title = $request->validated('title');
        $conversation->save();

        return $this->success(ConversationResource::make($conversation), 'Título atualizado com sucesso.');
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return $this->success(null, 'Conversa removida com sucesso.');
    }
}
