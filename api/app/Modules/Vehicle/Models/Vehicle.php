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
        'vehicle_type',
        'transmission',
        'odometer',
        'max_speed_kmh',
        'speed_hysteresis_percent',
        'speed_min_duration_seconds',
        'average_consumption',
        'tank_capacity',
        'crlv_file',
        'fipe_code',
        'fipe_model_year',
        'fipe_fuel',
        'fipe_reference_month',
        'fipe_value',
        'fipe_model',
        'fipe_brand',
        'fipe_score',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'vehicle_type' => 'integer',
            'odometer' => 'integer',
            'max_speed_kmh' => 'integer',
            'speed_hysteresis_percent' => 'integer',
            'speed_min_duration_seconds' => 'integer',
            'average_consumption' => 'float',
            'tank_capacity' => 'float',
            'fipe_score' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function equipment(): HasOne
    {
        return $this->hasOne(Equipment::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class)->orderBy('sort_order')->orderBy('id');
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
