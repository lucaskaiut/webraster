<?php

namespace App\Modules\Driver\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use BelongsToClient;
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'document',
        'phone',
        'email',
        'cnh_number',
        'cnh_expires_at',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cnh_expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): DriverFactory
    {
        return DriverFactory::new();
    }
}
