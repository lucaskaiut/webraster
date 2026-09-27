<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Http\Requests\SendChatMessageRequest;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\OutboundMessageService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class ChatMessageController extends ApiController
{
    public function __construct(private readonly OutboundMessageService $outbound) {}

    public function store(SendChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('reply', $conversation);

        $message = $this->outbound->sendText(
            $conversation,
            $request->user(),
            $request->validated('text'),
        );

        return $this->created(ChatMessageResource::make($message));
    }
}
