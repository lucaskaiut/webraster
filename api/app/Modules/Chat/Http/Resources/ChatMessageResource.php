<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'conversation_id' => $this->conversation?->uuid,
            'external_id' => $this->external_id,
            'direction' => $this->direction->value,
            'sender_type' => $this->sender_type->value,
            'message_type' => $this->message_type->value,
            'text' => $this->text,
            'media_url' => $this->media_url,
            'media_mime_type' => $this->media_mime_type,
            'media_name' => $this->media_name,
            'status' => $this->status->value,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
