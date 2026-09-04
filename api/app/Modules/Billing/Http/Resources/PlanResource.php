<?php

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (string) $this->price,
            'recurrence_value' => $this->recurrence_value,
            'recurrence_unit' => $this->recurrence_unit->value,
            'free_trial_days' => $this->free_trial_days,
            'trial_days' => $this->free_trial_days,
            'is_trial' => $this->hasTrial(),
            'requires_immediate_payment' => $this->requiresImmediatePayment(),
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
