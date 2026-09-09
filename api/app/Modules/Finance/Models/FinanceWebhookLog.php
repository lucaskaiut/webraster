<?php

namespace App\Modules\Finance\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FinanceWebhookLog extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_webhook_logs';

    protected $fillable = [
        'event_id',
        'event',
        'payment_id',
        'status',
        'payload',
        'error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
