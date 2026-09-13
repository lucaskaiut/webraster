<?php

namespace App\Modules\Client\Models;

use App\Modules\Client\Models\Scopes\ClientScope;
use App\Modules\Driver\Models\Driver;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'trade_name',
        'document',
        'state_registration',
        'email',
        'financial_email',
        'phone',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zip',
        'is_active',
        'plan_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientScope);
    }

    /**
     * Portal do cliente: o próprio registro é filtrado pela PK.
     */
    public function clientScopeColumn(): string
    {
        return 'id';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FinancePlan::class, 'plan_id');
    }

    public function financeSubscription(): HasOne
    {
        return $this->hasOne(FinanceSubscription::class)->latestOfMany();
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(ClientOrder::class);
    }

    public function clientContract(): HasOne
    {
        return $this->hasOne(ClientContract::class)->latestOfMany();
    }

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }
}
