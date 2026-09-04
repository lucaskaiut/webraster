<?php

namespace App\Modules\User\Models;

use App\Modules\ACL\Models\Concerns\HasRoles;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\MasterTenantAccessService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
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
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_master' => 'boolean',
        ];
    }

    public function canAccessTenant(Tenant|int $tenant): bool
    {
        return app(MasterTenantAccessService::class)->canAccess($this, $tenant);
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function availableTenants(): Collection
    {
        return app(MasterTenantAccessService::class)->availableTenants($this);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
