<?php

namespace App\Modules\ExternalApi\Models;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chamada feita pelo sistema a uma API externa (Google, ...).
 *
 * Registro de plataforma: não pertence a um tenant (o tenant_id só indica
 * em qual contexto a chamada aconteceu) e é visto apenas pelo umbrella.
 */
class ExternalApiRequest extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'service',
        'method',
        'url',
        'status_code',
        'succeeded',
        'duration_ms',
        'error',
        'request_headers',
        'request_body',
        'response_headers',
        'response_body',
        'origin',
        'tenant_id',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'succeeded' => 'boolean',
            'duration_ms' => 'integer',
            'request_headers' => 'array',
            'response_headers' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $days = max(1, (int) config('external_apis.logging.retention_days', 90));

        return static::query()->where('created_at', '<', now()->subDays($days));
    }
}
