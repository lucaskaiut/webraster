<?php

namespace App\Modules\Equipment\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Vehicle\Models\EquipmentAssignmentEvent;
use App\Modules\Vehicle\Models\Vehicle;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'vehicle_id',
        'imei',
        'traccar_device_id',
        'model',
        'iccid',
        'carrier',
        'is_active',
        'billing_suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'billing_suspended_at' => 'datetime',
        ];
    }

    public function isBillingSuspended(): bool
    {
        return $this->billing_suspended_at !== null;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function assignmentEvents(): HasMany
    {
        return $this->hasMany(EquipmentAssignmentEvent::class)->orderByDesc('occurred_at');
    }

    public function isAssigned(): bool
    {
        return $this->vehicle_id !== null;
    }

    protected static function newFactory(): EquipmentFactory
    {
        return EquipmentFactory::new();
    }
}
