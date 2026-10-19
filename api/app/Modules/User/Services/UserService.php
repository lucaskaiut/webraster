<?php

namespace App\Modules\User\Services;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\Scheduling\Services\Geocoder;
use App\Modules\Shared\Support\QuerySort;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(private readonly Geocoder $geocoder) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?string $sort = null,
        ?UserType $type = null,
        ?bool $technicianActive = null,
    ): LengthAwarePaginator {
        $query = User::query()
            ->with('roles.permissions')
            ->whereNull('client_id')
            ->when($type !== null, fn ($query) => $query->where('type', $type->value))
            ->when($technicianActive !== null, fn ($query) => $query->where('technician_active', $technicianActive))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        return QuerySort::apply($query, $sort, ['name', 'email', 'created_at'], 'name:asc')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $roleIds = $data['role_ids'];
            $workHours = $data['work_hours'] ?? [];
            $attributes = Arr::except($data, ['role_ids', 'work_hours']);

            $user = User::query()->create($attributes);
            $user->syncRoles($roleIds);

            if ($user->isTechnician()) {
                $this->ensureTechnicianRole($user);
                $this->syncWorkHours($user, $workHours);
                $this->geocodeBase($user);
            }

            return $user->refresh()->load(['roles.permissions', 'workHours']);
        });
    }

    /**
     * @param  array{name: string, email: string, phone: ?string, document: ?string, password: string, type?: string}  $data
     */
    public function createForTenant(Tenant $tenant, array $data): User
    {
        return $tenant->users()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $previousAddress = $user->baseAddress();
            $workHoursProvided = array_key_exists('work_hours', $data);
            $workHours = $data['work_hours'] ?? [];

            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }

            if (array_key_exists('role_ids', $data)) {
                $user->syncRoles($data['role_ids']);
                unset($data['role_ids']);
            }

            unset($data['work_hours']);

            $user->fill($data);
            $user->save();

            if ($user->isTechnician()) {
                $this->ensureTechnicianRole($user);

                if ($workHoursProvided) {
                    $this->syncWorkHours($user, $workHours);
                }

                if ($user->baseAddress() !== $previousAddress || $user->baseCoordinates() === null) {
                    $this->geocodeBase($user);
                }
            }

            return $user->refresh()->load(['roles.permissions', 'workHours']);
        });
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }

    /**
     * O tipo Técnico já garante o papel de execução das OS no aplicativo.
     */
    private function ensureTechnicianRole(User $user): void
    {
        if ($user->hasPermission(Permission::SERVICE_ORDER_EXECUTE)) {
            return;
        }

        $role = Role::query()
            ->where('name', DefaultRole::TECHNICIAN->value)
            ->first();

        if ($role !== null) {
            $user->assignRole($role);
        }
    }

    /**
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $hours
     */
    private function syncWorkHours(User $user, array $hours): void
    {
        $user->workHours()->delete();

        foreach ($hours as $hour) {
            $user->workHours()->create([
                'weekday' => (int) $hour['weekday'],
                'starts_at' => $hour['starts_at'],
                'ends_at' => $hour['ends_at'],
            ]);
        }
    }

    private function geocodeBase(User $user): void
    {
        $address = $user->baseAddress();
        $coordinates = $address !== '' ? $this->geocoder->geocode($address) : null;

        $user->forceFill([
            'latitude' => $coordinates?->latitude,
            'longitude' => $coordinates?->longitude,
        ])->saveQuietly();
    }
}
