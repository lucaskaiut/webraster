<?php

namespace App\Modules\Stock\Models;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuid;

    protected $table = 'stocks';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(User::class)->where('type', UserType::TECHNICIAN->value);
    }

    public function movementsFrom(): HasMany
    {
        return $this->hasMany(EquipmentStockMovement::class, 'from_stock_id');
    }

    public function movementsTo(): HasMany
    {
        return $this->hasMany(EquipmentStockMovement::class, 'to_stock_id');
    }

    protected static function newFactory(): StockFactory
    {
        return StockFactory::new();
    }
}
