<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinanceBilling;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinanceBilling
 */
class FinanceBillingResource extends JsonResource
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
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'client_id' => $this->client?->uuid,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->uuid,
                'name' => $this->client->name,
            ] : null),
            'subscription_id' => $this->subscription?->uuid,
            'amount_cents' => $this->amount_cents,
            'amount' => number_format($this->amount_cents / 100, 2, '.', ''),
            'discount_cents' => $this->discount_cents,
            'fine_cents' => $this->fine_cents,
            'interest_cents' => $this->interest_cents,
            'total_cents' => $this->totalCents(),
            'total' => number_format($this->totalCents() / 100, 2, '.', ''),
            'paid_amount_cents' => $this->paid_amount_cents,
            'due_at' => $this->due_at?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'invoice_url' => $this->invoice_url,
            'bank_slip_url' => $this->bank_slip_url,
            'pix_qr_code' => $this->pix_qr_code,
            'pix_copy_paste' => $this->pix_copy_paste,
            'description' => $this->description,
            'payment_gateway' => $this->payment_gateway,
            'gateway_payment_id' => $this->gateway_payment_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
