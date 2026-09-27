<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Models\Message;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ChatMessageCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Message $message,
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
        return 'message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ChatMessageResource::make($this->message->load('conversation'))->resolve();
    }
}
