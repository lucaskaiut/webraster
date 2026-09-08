<?php

namespace App\Modules\ServiceOrder\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\ServiceOrder\Enums\ServiceOrderPriority;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Enums\ServiceOrderType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use BelongsToClient;
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'number',
        'type',
        'status',
        'priority',
        'client_id',
        'vehicle_id',
        'equipment_id',
        'technician_id',
        'scheduled_start_at',
        'scheduled_end_at',
        'description',
        'notes',
        'execution_notes',
        'cancellation_reason',
        'created_by',
        'completed_by',
        'cancelled_by',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServiceOrderType::class,
            'status' => ServiceOrderStatus::class,
            'priority' => ServiceOrderPriority::class,
            'number' => 'integer',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getCodeAttribute(): string
    {
        return sprintf('OS-%06d', $this->number);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ServiceOrderHistory::class)->orderByDesc('created_at');
    }

    protected static function newFactory(): ServiceOrderFactory
    {
        return ServiceOrderFactory::new();
    }
}
