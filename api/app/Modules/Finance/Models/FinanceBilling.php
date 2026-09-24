<?php

namespace App\Modules\Finance\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\FinanceBillingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceBilling extends Model
{
    /** @use HasFactory<FinanceBillingFactory> */
    use BelongsToClient;

    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'finance_billings';

    protected $fillable = [
        'number',
        'subscription_id',
        'client_id',
        'status',
        'payment_method',
        'payment_gateway',
        'amount_cents',
        'discount_cents',
        'fine_cents',
        'interest_cents',
        'paid_amount_cents',
        'due_at',
        'paid_at',
        'cancelled_at',
        'gateway_payment_id',
        'invoice_url',
        'bank_slip_url',
        'pix_qr_code',
        'pix_copy_paste',
        'description',
        'gateway_payload',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => BillingStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount_cents' => 'integer',
            'discount_cents' => 'integer',
            'fine_cents' => 'integer',
            'interest_cents' => 'integer',
            'paid_amount_cents' => 'integer',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'gateway_payload' => 'array',
        ];
    }

    public function getCodeAttribute(): string
    {
        return (string) $this->number;
    }

    public function totalCents(): int
    {
        return max(0, $this->amount_cents - $this->discount_cents + $this->fine_cents + $this->interest_cents);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(FinanceSubscription::class, 'subscription_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FinanceBillingEvent::class, 'billing_id');
    }

    protected static function newFactory(): FinanceBillingFactory
    {
        return FinanceBillingFactory::new();
    }
}
