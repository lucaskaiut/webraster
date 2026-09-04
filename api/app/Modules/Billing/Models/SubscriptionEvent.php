<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\SubscriptionEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subscription_id',
        'event',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event' => SubscriptionEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
