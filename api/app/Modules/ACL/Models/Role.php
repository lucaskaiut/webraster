<?php

namespace App\Modules\ACL\Models;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Role extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function grantPermissions(Permission ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->permissions()->firstOrCreate(['permission' => $permission->value]);
        }

        $this->unsetRelation('permissions');
    }

    public function revokePermissions(Permission ...$permissions): void
    {
        $this->permissions()
            ->whereIn('permission', array_map(fn (Permission $permission) => $permission->value, $permissions))
            ->delete();

        $this->unsetRelation('permissions');
    }

    /**
     * @param  list<Permission>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $values = array_map(fn (Permission $permission) => $permission->value, $permissions);

        $this->permissions()->whereNotIn('permission', $values)->delete();

        $this->grantPermissions(...$permissions);
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->permissionValues()->contains($permission->value);
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionValues(): Collection
    {
        return $this->permissions
            ->pluck('permission')
            ->map(fn (Permission $permission) => $permission->value)
            ->values();
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }
}
