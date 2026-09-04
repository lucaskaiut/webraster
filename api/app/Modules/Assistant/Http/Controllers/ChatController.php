<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Http\Requests\SendMessageRequest;
use App\Modules\Assistant\Models\Conversation;
use App\Modules\Assistant\Services\AssistantService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends ApiController
{
    public function __construct(private readonly AssistantService $service) {}

    public function send(SendMessageRequest $request, Conversation $conversation): StreamedResponse
    {
        $this->authorize('view', $conversation);

        return $this->service->stream(
            $conversation,
            $request->user(),
            $request->validated('content'),
        );
    }
}
