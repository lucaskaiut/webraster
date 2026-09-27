<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Http\Resources\ChatConversationResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ChatConversationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Conversation $conversation,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenant->uuid}.staff")];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ChatConversationResource::make($this->conversation)->resolve();
    }
}
