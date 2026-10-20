<?php

namespace App\Modules\User\Models;

use App\Modules\ACL\Models\Concerns\HasRoles;
use App\Modules\Auth\Notifications\ResetPasswordNotification;
use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Scheduling\Services\Geocoder;
use App\Modules\Scheduling\Support\Coordinates;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Stock\Models\Stock;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\MasterTenantAccessService;
use App\Modules\User\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToClient;
    use BelongsToTenant;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'document',
        'password',
        'is_master',
        'is_primary',
        'client_id',
        'type',
        'zip',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'latitude',
        'longitude',
        'technician_active',
        'stock_id',
        'signature_path',
    ];

    protected $attributes = [
        'type' => UserType::STAFF->value,
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_master' => 'boolean',
            'is_primary' => 'boolean',
            'type' => UserType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'technician_active' => 'boolean',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function workHours(): HasMany
    {
        return $this->hasMany(UserWorkHour::class)
            ->orderBy('weekday')
            ->orderBy('starts_at');
    }

    public function baseAddress(): string
    {
        return Geocoder::formatAddress($this->only([
            'street',
            'number',
            'neighborhood',
            'city',
            'state',
            'zip',
        ]));
    }

    public function baseCoordinates(): ?Coordinates
    {
        return Coordinates::tryFrom($this->latitude, $this->longitude);
    }

    public function canAccessTenant(Tenant|int $tenant): bool
    {
        return app(MasterTenantAccessService::class)->canAccess($this, $tenant);
    }

    public function isClientUser(): bool
    {
        return $this->client_id !== null;
    }

    public function isTechnician(): bool
    {
        return $this->type === UserType::TECHNICIAN;
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function availableTenants(): Collection
    {
        return app(MasterTenantAccessService::class)->availableTenants($this);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
