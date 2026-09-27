<?php

namespace App\Modules\Crm\Events;

use App\Modules\Crm\Http\Resources\LeadResource;
use App\Modules\Crm\Models\Lead;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class LeadStageChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Lead $lead,
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
        return 'lead.stage_changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return LeadResource::make($this->lead->load(['contact', 'stage', 'pipeline', 'owner']))->resolve();
    }
}
