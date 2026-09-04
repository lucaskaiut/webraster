<?php

namespace App\Modules\Assistant\Http\Resources;

use App\Modules\Assistant\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'role' => $this->role?->value,
            'content' => $this->content,
            'tool_calls' => $this->tool_calls,
            'tool_results' => $this->tool_results,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
