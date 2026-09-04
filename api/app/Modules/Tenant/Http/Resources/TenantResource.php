<?php

namespace App\Modules\Tenant\Http\Resources;

use App\Modules\Billing\Http\Resources\SubscriptionResource;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'domain' => $this->domain,
            'is_umbrella' => $this->isUmbrella(),
            'users_count' => $this->whenCounted('users'),
            'subscription' => SubscriptionResource::make($this->whenLoaded('subscription')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
