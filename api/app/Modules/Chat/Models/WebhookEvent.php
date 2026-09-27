<?php

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    protected $table = 'chat_webhook_events';

    protected $fillable = [
        'connection_id',
        'dedupe_key',
        'event_type',
        'status',
        'payload',
        'received_at',
        'processed_at',
        'dispatched_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => WebhookEventStatus::class,
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MessagingConnection::class, 'connection_id');
    }

    public function markDispatched(): void
    {
        $this->forceFill(['dispatched_at' => now()])->save();
    }
}
