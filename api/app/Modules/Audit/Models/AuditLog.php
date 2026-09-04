<?php

namespace App\Modules\Audit\Models;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'selected_tenant_id',
        'action',
        'ip',
        'user_agent',
        'entity_type',
        'entity_id',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'details' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'selected_tenant_id');
    }
}
