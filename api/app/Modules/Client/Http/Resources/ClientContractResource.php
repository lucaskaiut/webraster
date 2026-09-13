<?php

namespace App\Modules\Client\Http\Resources;

use App\Modules\Client\Models\ClientContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientContract
 */
class ClientContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'contract_id' => $this->contract?->uuid,
            'contract_name' => $this->contract?->name,
            'valid_until' => $this->valid_until?->toDateString(),
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
