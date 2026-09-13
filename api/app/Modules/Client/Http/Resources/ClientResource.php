<?php

namespace App\Modules\Client\Http\Resources;

use App\Modules\Client\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'document' => $this->document,
            'state_registration' => $this->state_registration,
            'email' => $this->email,
            'financial_email' => $this->financial_email,
            'phone' => $this->phone,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'is_active' => (bool) $this->is_active,
            'plan_id' => $this->plan?->uuid,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan ? [
                'id' => $this->plan->uuid,
                'name' => $this->plan->name,
                'amount_cents' => $this->plan->amount_cents,
                'periodicity' => $this->plan->periodicity?->value,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
