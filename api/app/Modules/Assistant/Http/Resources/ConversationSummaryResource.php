<?php

namespace App\Modules\Assistant\Http\Resources;

use App\Modules\Assistant\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Conversation
 */
class ConversationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $last = $this->whenLoaded('latestMessage');

        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'message_count' => $this->messages_count ?? null,
            'last_message' => $last?->content !== null ? Str::limit($last->content, 120) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
