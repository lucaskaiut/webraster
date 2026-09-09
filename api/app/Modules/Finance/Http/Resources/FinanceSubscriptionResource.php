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
            'contract_id' => $this->contract?->uuid,
            'contract' => $this->whenLoaded('contract', fn () => $this->contract ? [
                'id' => $this->contract->uuid,
                'code' => $this->contract->code,
                'status' => $this->contract->status?->value,
                'amount_cents' => $this->contract->amount_cents,
                'plan' => $this->contract->relationLoaded('plan') && $this->contract->plan ? [
                    'id' => $this->contract->plan->uuid,
                    'name' => $this->contract->plan->name,
                ] : null,
            ] : null),
            'periodicity' => $this->periodicity?->value,
            'periodicity_label' => $this->periodicity?->label(),
            'next_billing_at' => $this->next_billing_at?->toDateString(),
            'last_billing_at' => $this->last_billing_at?->toDateString(),
            'gateway_subscription_id' => $this->gateway_subscription_id,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
