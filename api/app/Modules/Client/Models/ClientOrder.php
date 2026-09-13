<?php

namespace App\Modules\Client\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientOrder extends Model
{
    use BelongsToClient;
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'finance_subscription_id',
        'due_day',
        'periodicity',
        'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'due_day' => 'integer',
            'periodicity' => BillingPeriodicity::class,
            'total_cents' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientOrderItem::class)->orderBy('id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(FinanceSubscription::class, 'finance_subscription_id');
    }
}
