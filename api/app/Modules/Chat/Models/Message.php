<?php

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Enums\MessageDirection;
use App\Modules\Chat\Enums\MessageStatus;
use App\Modules\Chat\Enums\MessageType;
use App\Modules\Chat\Enums\SenderType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'chat_messages';

    protected $fillable = [
        'conversation_id',
        'external_id',
        'direction',
        'sender_type',
        'sender_user_id',
        'message_type',
        'text',
        'media_url',
        'media_mime_type',
        'media_name',
        'media_size',
        'reply_to_message_id',
        'status',
        'sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'sender_type' => SenderType::class,
            'message_type' => MessageType::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }
}
