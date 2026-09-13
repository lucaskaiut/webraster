<?php

namespace App\Modules\Finance\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\FinancePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancePlan extends Model
{
    /** @use HasFactory<FinancePlanFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'finance_plans';

    protected $fillable = [
        'name',
        'description',
        'amount_cents',
        'periodicity',
        'device_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'device_limit' => 'integer',
            'is_active' => 'boolean',
            'periodicity' => BillingPeriodicity::class,
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(FinanceSubscription::class, 'plan_id');
    }

    protected static function newFactory(): FinancePlanFactory
    {
        return FinancePlanFactory::new();
    }
}
