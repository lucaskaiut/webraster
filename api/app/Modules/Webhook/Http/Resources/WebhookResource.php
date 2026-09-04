<?php

namespace App\Modules\Webhook\Http\Resources;

use App\Modules\Webhook\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Webhook
 */
class WebhookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'method' => $this->method,
            'event' => $this->event,
            'headers' => $this->headers,
            'query_params' => $this->query_params,
            'body_template' => $this->body_template,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
