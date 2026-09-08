<?php

namespace App\Modules\ServiceOrder\Models;

use App\Modules\ServiceOrder\Enums\ServiceOrderHistoryAction;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderHistory extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'service_order_id',
        'user_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'action' => ServiceOrderHistoryAction::class,
            'meta' => 'array',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
