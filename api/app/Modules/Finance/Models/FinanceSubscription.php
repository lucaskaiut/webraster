<?php

namespace App\Modules\Finance\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Finance\Enums\BillingPeriodicity;
use App\Modules\Finance\Enums\SubscriptionStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\FinanceSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceSubscription extends Model
{
    /** @use HasFactory<FinanceSubscriptionFactory> */
    use BelongsToClient;
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'finance_subscriptions';

    protected $fillable = [
        'contract_id',
        'client_id',
        'status',
        'periodicity',
        'next_billing_at',
        'last_billing_at',
        'gateway_subscription_id',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'periodicity' => BillingPeriodicity::class,
            'next_billing_at' => 'date',
            'last_billing_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(FinanceContract::class, 'contract_id');
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(FinanceReceivable::class, 'subscription_id');
    }

    protected static function newFactory(): FinanceSubscriptionFactory
    {
        return FinanceSubscriptionFactory::new();
    }
}
