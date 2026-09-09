<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\TenantAsaasConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantAsaasConfig
 */
class TenantAsaasConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $apiKey = $this->api_key;
        $masked = null;
        if (filled($apiKey)) {
            $len = strlen((string) $apiKey);
            $masked = $len <= 8
                ? str_repeat('*', $len)
                : substr((string) $apiKey, 0, 4).str_repeat('*', max(0, $len - 8)).substr((string) $apiKey, -4);
        }

        return [
            'id' => $this->uuid,
            'environment' => $this->environment?->value,
            'environment_label' => $this->environment?->label(),
            'api_key_masked' => $masked,
            'has_api_key' => filled($apiKey),
            'has_webhook_token' => filled($this->webhook_token),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
