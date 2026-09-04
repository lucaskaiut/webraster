<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\RecurrenceUnit;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'price',
        'recurrence_value',
        'recurrence_unit',
        'free_trial_days',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'recurrence_value' => 'integer',
            'recurrence_unit' => RecurrenceUnit::class,
            'free_trial_days' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasTrial(): bool
    {
        return $this->free_trial_days > 0;
    }

    /**
     * Planos com trial não geram cobrança no cadastro; a fatura nasce após o teste.
     */
    public function requiresImmediatePayment(): bool
    {
        return ! $this->hasTrial();
    }

    public function calculateNextBillingFrom(CarbonImmutable $from): CarbonImmutable
    {
        return match ($this->recurrence_unit) {
            RecurrenceUnit::DAYS => $from->addDays($this->recurrence_value),
            RecurrenceUnit::WEEKS => $from->addWeeks($this->recurrence_value),
            RecurrenceUnit::MONTHS => $from->addMonths($this->recurrence_value),
            RecurrenceUnit::YEARS => $from->addYears($this->recurrence_value),
        };
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
