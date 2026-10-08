<?php

namespace App\Modules\Alert\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Som escolhido pelo usuário para as notificações de um tipo de alerta.
 * A chave `sound` pertence ao catálogo de config('notification.push.sounds').
 */
class AlertSoundPreference extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'sound',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
