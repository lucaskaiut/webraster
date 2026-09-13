<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinanceSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinanceSubscription
 */
class FinanceSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'client_id' => $this->client?->uuid,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->uuid,
                'name' => $this->client->name,
            ] : null),
            'plan_id' => $this->plan?->uuid,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan ? [
                'id' => $this->plan->uuid,
                'name' => $this->plan->name,
                'amount_cents' => $this->plan->amount_cents,
                'periodicity' => $this->plan->periodicity?->value,
            ] : null),
            'plan_name' => $this->plan_name,
            'plan_price_cents' => $this->plan_price_cents,
            'plan_periodicity' => $this->plan_periodicity?->value,
            'plan_periodicity_label' => $this->plan_periodicity?->label(),
            'due_day' => $this->due_day,
            'block_on_overdue' => (bool) $this->block_on_overdue,
            'block_after_days' => $this->block_after_days,
            'next_billing_at' => $this->next_billing_at?->toDateString(),
            'last_billed_at' => $this->last_billed_at?->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'gateway_subscription_id' => $this->gateway_subscription_id,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
