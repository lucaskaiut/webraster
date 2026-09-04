<?php

namespace App\Modules\Webhook\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'url',
        'method',
        'event',
        'headers',
        'query_params',
        'body_template',
        'is_active',
        'secret',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'query_params' => 'array',
            'body_template' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }
}
