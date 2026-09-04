<?php

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'details' => $this->details,
            'ip' => $this->ip,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->uuid,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
