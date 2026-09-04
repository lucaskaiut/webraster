<?php

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'gateway' => $this->gateway,
            'amount' => (string) $this->amount,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method?->value,
            'external_id' => $this->external_id,
            'pix_code' => $this->pix_code,
            'pix_qrcode' => $this->pix_qrcode,
            'invoice_url' => $this->metadata['invoice_url'] ?? null,
            'awaiting_payment_method' => $this->awaitsPaymentMethod(),
            'due_date' => $this->due_date?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'subscription' => SubscriptionResource::make($this->whenLoaded('subscription')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
