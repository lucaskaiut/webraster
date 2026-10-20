<?php

namespace App\Modules\Stock\Models;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Stock\Enums\EquipmentStockMovementType;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentStockMovement extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'equipment_stock_movements';

    protected $fillable = [
        'equipment_id',
        'from_stock_id',
        'to_stock_id',
        'type',
        'description',
        'moved_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EquipmentStockMovementType::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function fromStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'from_stock_id');
    }

    public function toStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'to_stock_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by')->withTrashed();
    }
}
