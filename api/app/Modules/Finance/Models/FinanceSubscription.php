<?php

namespace App\Modules\Finance\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Shared\Subscription\Enums\SubscriptionStatus;
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
        'client_id',
        'plan_id',
        'plan_name',
        'plan_price_cents',
        'plan_periodicity',
        'status',
        'due_day',
        'block_on_overdue',
        'block_after_days',
        'last_billed_at',
        'next_billing_at',
        'started_at',
        'cancelled_at',
        'gateway_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'plan_periodicity' => BillingPeriodicity::class,
            'plan_price_cents' => 'integer',
            'due_day' => 'integer',
            'block_on_overdue' => 'boolean',
            'block_after_days' => 'integer',
            'last_billed_at' => 'date',
            'next_billing_at' => 'date',
            'started_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FinancePlan::class, 'plan_id');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(FinanceBilling::class, 'subscription_id');
    }

    public function allowsAccess(): bool
    {
        return $this->status?->allowsAccess() ?? false;
    }

    protected static function newFactory(): FinanceSubscriptionFactory
    {
        return FinanceSubscriptionFactory::new();
    }
}
