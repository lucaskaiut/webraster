<?php

namespace App\Modules\Assistant\Models;

use App\Modules\Assistant\Enums\MessageRole;
use App\Modules\Shared\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasUuid;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_calls',
        'tool_results',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'tool_calls' => 'array',
            'tool_results' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
