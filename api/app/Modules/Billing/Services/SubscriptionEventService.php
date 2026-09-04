<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\SubscriptionEventType;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionEvent;

class SubscriptionEventService
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        Subscription $subscription,
        SubscriptionEventType $event,
        ?array $payload = null,
    ): SubscriptionEvent {
        return SubscriptionEvent::query()->create([
            'subscription_id' => $subscription->getKey(),
            'event' => $event,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
