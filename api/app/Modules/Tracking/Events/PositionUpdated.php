<?php

namespace App\Modules\Tracking\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PositionUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly ?string $tenantUuid,
        public readonly ?string $clientUuid,
        public readonly array $payload,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->tenantUuid !== null) {
            $channels[] = new PrivateChannel("tenant.{$this->tenantUuid}.staff");
        }

        if ($this->clientUuid !== null) {
            $channels[] = new PrivateChannel("client.{$this->clientUuid}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'position.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
