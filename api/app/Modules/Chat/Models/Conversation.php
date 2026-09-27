<?php

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Enums\ChatType;
use App\Modules\Chat\Enums\ConversationAiMode;
use App\Modules\Crm\Models\Lead;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'chat_conversations';

    protected $fillable = [
        'lead_id',
        'contact_id',
        'connection_id',
        'external_conversation_id',
        'chat_type',
        'status',
        'ai_mode',
        'assigned_user_id',
        'unread_count',
        'last_message_at',
        'last_inbound_message_at',
        'last_outbound_message_at',
    ];

    protected function casts(): array
    {
        return [
            'chat_type' => ChatType::class,
            'ai_mode' => ConversationAiMode::class,
            'unread_count' => 'integer',
            'last_message_at' => 'datetime',
            'last_inbound_message_at' => 'datetime',
            'last_outbound_message_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MessagingConnection::class, 'connection_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function isAiEligible(): bool
    {
        if ($this->ai_mode === ConversationAiMode::HUMAN_HANDOFF
            || $this->ai_mode === ConversationAiMode::AI_DISABLED) {
            return false;
        }

        return $this->lead?->ai_enabled ?? false;
    }
}
