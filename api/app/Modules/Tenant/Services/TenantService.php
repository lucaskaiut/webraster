<?php

namespace App\Modules\Tenant\Services;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TenantService
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly UserService $users,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * @param  array{name: string, document: string, email: string, phone: ?string, domain: string, parent_id?: int|null}  $data
     */
    public function create(array $data): Tenant
    {
        if (! array_key_exists('parent_id', $data)) {
            $data['parent_id'] = $this->resolveParentId();
        }

        return Tenant::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        unset($data['parent_id']);

        $tenant->fill($data);
        $tenant->save();

        return $tenant->refresh();
    }

    /**
     * Lista os tenants filhos do umbrella informado.
     */
    public function paginateChildren(Tenant $umbrella, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Tenant::query()
            ->where('parent_id', $umbrella->getKey())
            ->withCount('users')
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('domain', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Provisiona um tenant filho sob o umbrella: tenant + perfis padrão +
     * usuário administrador (não master) e, opcionalmente, assinatura.
     *
     * @param  array{name: string, document: string, email: string, phone: ?string, domain: string}  $tenantData
     * @param  array{name: string, email: string, phone?: ?string, document?: ?string, password: string}  $userData
     * @return array{tenant: Tenant, user: User, subscription: ?Subscription}
     */
    public function createChild(Tenant $umbrella, array $tenantData, array $userData, ?string $planId = null): array
    {
        return DB::transaction(function () use ($umbrella, $tenantData, $userData, $planId): array {
            $tenant = $this->create([
                ...$tenantData,
                'parent_id' => $umbrella->getKey(),
            ]);

            $roles = $this->roles->createDefaultRolesFor($tenant);

            $user = $this->users->createForTenant($tenant, $userData);
            $user->assignRole($roles[DefaultRole::ADMINISTRATOR->value]);

            $subscription = $planId !== null
                ? $this->subscriptions->createForTenant($tenant, $planId)
                : null;

            return [
                'tenant' => $tenant,
                'user' => $user->load('roles.permissions'),
                'subscription' => $subscription,
            ];
        });
    }

    /**
     * Novos tenants ficam sob o primeiro cadastrado na base.
     * O primeiro tenant permanece como raiz (parent_id nulo).
     */
    private function resolveParentId(): ?int
    {
        $firstTenantId = Tenant::query()->orderBy('id')->value('id');

        return $firstTenantId !== null ? (int) $firstTenantId : null;
    }
}
