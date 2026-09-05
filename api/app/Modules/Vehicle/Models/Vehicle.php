<?php

namespace App\Modules\Vehicle\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use BelongsToClient;
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'plate',
        'chassis',
        'renavam',
        'brand',
        'model',
        'color',
        'year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function equipment(): HasOne
    {
        return $this->hasOne(Equipment::class);
    }

    public function assignmentEvents(): HasMany
    {
        return $this->hasMany(EquipmentAssignmentEvent::class)->orderByDesc('occurred_at');
    }

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }
}
