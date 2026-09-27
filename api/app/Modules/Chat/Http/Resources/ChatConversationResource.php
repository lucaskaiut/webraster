<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Crm\Http\Resources\LeadSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ChatConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'chat_type' => $this->chat_type->value,
            'ai_mode' => $this->ai_mode->value,
            'unread_count' => $this->unread_count,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'contact' => [
                'id' => $this->contact?->uuid,
                'name' => $this->contact?->name,
                'phone' => $this->contact?->phone,
                'avatar_url' => $this->contact?->avatar_url,
            ],
            'lead' => $this->whenLoaded('lead', fn () => LeadSummaryResource::make($this->lead)),
            'last_message' => $this->whenLoaded('messages', function () {
                $last = $this->messages->sortByDesc('sent_at')->first();

                return $last ? ChatMessageResource::make($last) : null;
            }),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => [
                'id' => $this->assignedUser?->uuid,
                'name' => $this->assignedUser?->name,
            ]),
        ];
    }
}
