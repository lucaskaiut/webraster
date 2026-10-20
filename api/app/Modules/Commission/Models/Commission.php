<?php

namespace App\Modules\Commission\Models;

use App\Modules\Commission\Enums\CommissionStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'service_order_id',
        'technician_id',
        'status',
        'base_amount_cents',
        'amount_cents',
        'breakdown',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'base_amount_cents' => 'integer',
            'amount_cents' => 'integer',
            'breakdown' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    protected static function newFactory(): CommissionFactory
    {
        return CommissionFactory::new();
    }
}
