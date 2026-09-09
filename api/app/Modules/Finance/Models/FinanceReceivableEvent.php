<?php

namespace App\Modules\Finance\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceReceivableEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_receivable_events';

    protected $fillable = [
        'receivable_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(FinanceReceivable::class, 'receivable_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
