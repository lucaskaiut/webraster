<?php

namespace App\Modules\Finance\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Finance\Enums\BillingPeriodicity;
use App\Modules\Finance\Enums\ContractStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Database\Factories\FinanceContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceContract extends Model
{
    /** @use HasFactory<FinanceContractFactory> */
    use BelongsToClient;
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'finance_contracts';

    protected $fillable = [
        'number',
        'client_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'periodicity',
        'due_day',
        'amount_cents',
        'discount_cents',
        'fine_percent',
        'interest_percent',
        'device_quantity',
        'auto_renew',
        'block_on_overdue',
        'block_after_days',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => ContractStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'periodicity' => BillingPeriodicity::class,
            'due_day' => 'integer',
            'amount_cents' => 'integer',
            'discount_cents' => 'integer',
            'fine_percent' => 'decimal:2',
            'interest_percent' => 'decimal:2',
            'device_quantity' => 'integer',
            'auto_renew' => 'boolean',
            'block_on_overdue' => 'boolean',
            'block_after_days' => 'integer',
        ];
    }

    public function getCodeAttribute(): string
    {
        return sprintf('CTR-%06d', $this->number);
    }

    public function netAmountCents(): int
    {
        return max(0, $this->amount_cents - $this->discount_cents);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FinancePlan::class, 'plan_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(FinanceSubscription::class, 'contract_id');
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(FinanceReceivable::class, 'contract_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): FinanceContractFactory
    {
        return FinanceContractFactory::new();
    }
}
