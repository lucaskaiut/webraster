<?php

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'payment_gateway' => $this->payment_gateway,
            'recurring' => (bool) $this->recurring,
            'started_at' => $this->started_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'last_billed_at' => $this->last_billed_at?->toIso8601String(),
            'next_billing_at' => $this->next_billing_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'plan' => PlanResource::make($this->whenLoaded('plan')),
            'events' => SubscriptionEventResource::collection($this->whenLoaded('events')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
