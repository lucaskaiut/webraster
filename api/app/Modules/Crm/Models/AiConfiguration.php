<?php

namespace App\Modules\Crm\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiConfiguration extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'crm_ai_configurations';

    protected $fillable = [
        'enabled',
        'api_endpoint',
        'api_key',
        'system_prompt',
        'model',
        'temperature',
        'max_tokens',
        'settings',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'api_key' => 'encrypted',
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'settings' => 'array',
        ];
    }
}
