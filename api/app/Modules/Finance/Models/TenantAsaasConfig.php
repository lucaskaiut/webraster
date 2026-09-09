<?php

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\AsaasEnvironment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantAsaasConfig extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'tenant_asaas_configs';

    protected $fillable = [
        'environment',
        'api_key',
        'webhook_token',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
        'webhook_token',
    ];

    protected function casts(): array
    {
        return [
            'environment' => AsaasEnvironment::class,
            'api_key' => 'encrypted',
            'webhook_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
