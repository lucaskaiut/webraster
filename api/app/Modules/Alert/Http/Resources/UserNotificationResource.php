<?php

namespace App\Modules\Alert\Http\Resources;

use App\Modules\Alert\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserNotification
 */
class UserNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'alert' => AlertResource::make($this->whenLoaded('alert')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
