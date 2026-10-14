<?php

namespace App\Modules\ServiceOrder\Models;

use App\Modules\Service\Models\Service;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderItem extends Model
{
    /** @use HasFactory<ServiceOrderItemFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'service_order_id',
        'service_id',
        'name',
        'quantity',
        'unit_amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_cents' => 'integer',
        ];
    }

    public function totalCents(): int
    {
        return max(0, $this->quantity * $this->unit_amount_cents);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected static function newFactory(): ServiceOrderItemFactory
    {
        return ServiceOrderItemFactory::new();
    }
}
