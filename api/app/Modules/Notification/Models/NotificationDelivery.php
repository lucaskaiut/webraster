<?php

namespace App\Modules\Notification\Models;

use App\Modules\Alert\Models\UserNotification;
use App\Modules\Notification\Enums\NotificationChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'user_notification_id',
        'user_id',
        'device_token_id',
        'channel',
        'status',
        'provider',
        'provider_message_id',
        'error',
        'sent_at',
        'delivered_at',
        'receipt_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationDeliveryStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'receipt_checked_at' => 'datetime',
        ];
    }

    public function userNotification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class);
    }
}
