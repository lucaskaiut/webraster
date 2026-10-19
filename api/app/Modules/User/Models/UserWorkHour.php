<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jornada semanal do técnico, vinculada diretamente ao usuário.
 */
class UserWorkHour extends Model
{
    protected $fillable = [
        'user_id',
        'weekday',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Horário no formato HH:MM (o banco pode devolver HH:MM:SS).
     */
    public static function normalizeTime(string $time): string
    {
        return substr($time, 0, 5);
    }
}
