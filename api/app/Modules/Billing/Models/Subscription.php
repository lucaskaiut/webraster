<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'payment_gateway',
        'credit_card_token',
        'recurring',
        'status',
        'started_at',
        'trial_ends_at',
        'last_billed_at',
        'next_billing_at',
        'cancelled_at',
    ];

    protected $hidden = [
        'credit_card_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'credit_card_token' => 'encrypted',
            'recurring' => 'boolean',
            'started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'last_billed_at' => 'datetime',
            'next_billing_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withoutTenancy();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function allowsAccess(): bool
    {
        return $this->status->allowsAccess();
    }

    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::TRIALING
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}
