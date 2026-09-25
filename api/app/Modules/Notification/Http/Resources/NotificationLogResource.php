<?php

namespace App\Modules\Notification\Http\Resources;

use App\Modules\Alert\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notificação enviada (log para o painel), com destinatário e canais.
 *
 * @mixin UserNotification
 */
class NotificationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'source' => $this->source,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'clicked_at' => $this->clicked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->uuid,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'deliveries' => NotificationDeliveryResource::collection(
                $this->whenLoaded('deliveries'),
            ),
        ];
    }
}
