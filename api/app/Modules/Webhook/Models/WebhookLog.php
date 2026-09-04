<?php

namespace App\Modules\Webhook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $fillable = [
        'webhook_id',
        'status_code',
        'response_body',
        'request_payload',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'request_payload' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
