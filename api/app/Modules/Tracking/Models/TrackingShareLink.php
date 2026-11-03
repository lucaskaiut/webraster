<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Link público e temporário (máx. 24h) para acompanhar veículos no mapa.
 */
class TrackingShareLink extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const MAX_HOURS = 24;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'token_hash',
        'expires_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'tracking_share_link_vehicle');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
