<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ajuste da jornada do técnico em uma data: antecipa o início do primeiro
 * intervalo e/ou estende o fim do último. Não altera a jornada semanal.
 */
class UserWorkDayOverride extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'starts_at',
        'ends_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
