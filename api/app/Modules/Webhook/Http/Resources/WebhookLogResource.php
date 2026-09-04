<?php

namespace App\Modules\Webhook\Http\Resources;

use App\Modules\Webhook\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebhookLog
 */
class WebhookLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status_code' => $this->status_code,
            'response_body' => $this->response_body,
            'request_payload' => $this->request_payload,
            'error_message' => $this->error_message,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
