<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Chat\Support\EvolutionConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MessagingConnection */
class MessagingConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'provider' => $this->provider,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'instance_name' => $this->instance_name,
            'connection_status' => $this->connection_status,
            'capabilities' => $this->capabilities,
            'is_active' => $this->is_active,
            'webhook_url' => $this->webhookUrl(),
            'has_api_key' => filled($this->credential('api_key'))
                || ($this->provider === 'evolution' && EvolutionConfig::isConfigured()),
        ];
    }
}
