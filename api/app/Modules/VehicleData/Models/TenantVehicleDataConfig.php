<?php

namespace App\Modules\VehicleData\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantVehicleDataConfig extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'tenant_vehicle_data_configs';

    protected $fillable = [
        'provider',
        'is_active',
        'credentials',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        $credentials = is_array($this->credentials) ? $this->credentials : [];

        return $credentials[$key] ?? $default;
    }
}
