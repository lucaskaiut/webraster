<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinanceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinanceContract
 */
class FinanceContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'number' => $this->number,
            'code' => $this->code,
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
            ] : null),
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription
                ? FinanceSubscriptionResource::make($this->subscription)
                : null),
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'periodicity' => $this->periodicity?->value,
            'periodicity_label' => $this->periodicity?->label(),
            'due_day' => $this->due_day,
            'amount_cents' => $this->amount_cents,
            'amount' => number_format($this->amount_cents / 100, 2, '.', ''),
            'discount_cents' => $this->discount_cents,
            'net_amount_cents' => $this->netAmountCents(),
            'fine_percent' => $this->fine_percent,
            'interest_percent' => $this->interest_percent,
            'device_quantity' => $this->device_quantity,
            'auto_renew' => $this->auto_renew,
            'block_on_overdue' => $this->block_on_overdue,
            'block_after_days' => $this->block_after_days,
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->uuid,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
