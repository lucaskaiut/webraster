<?php

namespace App\Modules\Service\Models;

use App\Modules\Service\Enums\CommissionType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'amount_cents',
        'estimated_minutes',
        'commission_type',
        'commission_value',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'estimated_minutes' => 'integer',
            'commission_type' => CommissionType::class,
            'commission_value' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function linkedServices(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'service_linked_services', 'service_id', 'linked_service_id')
            ->withTimestamps()
            ->orderBy('name');
    }

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }
}
