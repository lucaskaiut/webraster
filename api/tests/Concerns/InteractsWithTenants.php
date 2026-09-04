<?php

namespace Tests\Concerns;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\User\Models\User;

trait InteractsWithTenants
{
    protected function createTenantWithRoles(array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->create($attributes);

        app(RoleService::class)->createDefaultRolesFor($tenant);

        return $tenant;
    }

    protected function createAdmin(Tenant $tenant, array $attributes = []): User
    {
        $user = User::factory()->for($tenant)->create($attributes);
        $user->assignRole($this->roleFor($tenant, DefaultRole::ADMINISTRATOR));

        return $user;
    }

    protected function createMember(Tenant $tenant, array $attributes = []): User
    {
        $user = User::factory()->for($tenant)->create($attributes);
        $user->assignRole($this->roleFor($tenant, DefaultRole::USER));

        return $user;
    }

    protected function createMaster(Tenant $umbrella, array $attributes = []): User
    {
        $user = User::factory()
            ->for($umbrella)
            ->master()
            ->create($attributes);

        $user->assignRole($this->roleFor($umbrella, DefaultRole::ADMINISTRATOR));

        return $user;
    }

    protected function createChildTenant(Tenant $parent, array $attributes = []): Tenant
    {
        return $this->createTenantWithRoles([
            ...$attributes,
            'parent_id' => $parent->getKey(),
        ]);
    }

    protected function roleFor(Tenant $tenant, DefaultRole $role): Role
    {
        return Role::query()
            ->forTenant($tenant)
            ->where('name', $role->value)
            ->firstOrFail();
    }

    protected function forgetTenantContext(): void
    {
        app(CurrentTenant::class)->forget();
    }

    /**
     * @return array{0: Tenant, 1: Tenant}
     */
    protected function createOperationalChild(?Tenant $umbrella = null, array $childAttributes = []): array
    {
        $umbrella ??= $this->createTenantWithRoles();

        $child = $this->createChildTenant($umbrella, $childAttributes);

        $plan = Plan::factory()->forTenant($umbrella)->create();
        Subscription::factory()->forTenant($child)->forPlan($plan)->active()->create();

        return [$umbrella, $child];
    }
}
