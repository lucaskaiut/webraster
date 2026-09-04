<?php

namespace Tests\Feature\Tenant;

use App\Modules\Billing\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantChildrenTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_master_lists_only_own_child_tenants(): void
    {
        $umbrella = $this->createTenantWithRoles(['name' => 'Grupo XPTO']);
        $this->createChildTenant($umbrella, ['name' => 'Empresa A']);
        $this->createChildTenant($umbrella, ['name' => 'Empresa B']);

        $other = $this->createTenantWithRoles(['name' => 'Outro Grupo', 'domain' => 'outro.com.br']);
        $this->createChildTenant($other, ['name' => 'Empresa Z']);

        Sanctum::actingAs($this->createMaster($umbrella));

        $response = $this->getJson('/api/tenant/children')->assertOk();

        $this->assertSame(2, $response->json('meta.total'));

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Empresa A', 'Empresa B'], $names);
    }

    public function test_master_creates_child_tenant_with_admin(): void
    {
        $umbrella = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'Empresa Nova',
                'document' => '04.252.011/0001-10',
                'email' => 'contato@nova.com',
                'phone' => '41999999999',
                'domain' => 'nova.com.br',
            ],
            'user' => [
                'name' => 'Admin Nova',
                'email' => 'admin@nova.com',
                'phone' => '41999999999',
                'document' => '529.982.247-25',
                'password' => '12345678',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.tenant.name', 'Empresa Nova')
            ->assertJsonPath('data.tenant.is_umbrella', false)
            ->assertJsonPath('data.user.email', 'admin@nova.com')
            ->assertJsonPath('data.user.is_master', false);

        $child = Tenant::query()->where('domain', 'nova.com.br')->firstOrFail();

        $this->assertSame($umbrella->getKey(), $child->parent_id);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@nova.com',
            'tenant_id' => $child->getKey(),
            'is_master' => false,
        ]);
    }

    public function test_master_creates_child_tenant_with_plan(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $plan = Plan::factory()->forTenant($umbrella)->create(['free_trial_days' => 7]);

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'Empresa Paga',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@paga.com',
                'phone' => '41999999999',
                'domain' => 'paga.com.br',
            ],
            'user' => [
                'name' => 'Admin Paga',
                'email' => 'admin@paga.com',
                'password' => '12345678',
            ],
            'plan_id' => $plan->uuid,
        ])->assertCreated();

        $child = Tenant::query()->where('domain', 'paga.com.br')->firstOrFail();

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $child->getKey(),
            'plan_id' => $plan->getKey(),
        ]);
    }

    public function test_regular_admin_cannot_manage_child_tenants(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $this->createChildTenant($umbrella);

        Sanctum::actingAs($this->createAdmin($umbrella));

        $this->getJson('/api/tenant/children')->assertForbidden();
        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'X',
                'document' => '04.252.011/0001-10',
                'email' => 'x@x.com',
                'phone' => '41999999999',
                'domain' => 'x.com.br',
            ],
            'user' => [
                'name' => 'X',
                'email' => 'admin@x.com',
                'password' => '12345678',
            ],
        ])->assertForbidden();
    }

    public function test_child_tenant_admin_cannot_create_children(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($child));

        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'X',
                'document' => '04.252.011/0001-10',
                'email' => 'x@x.com',
                'phone' => '41999999999',
                'domain' => 'x.com.br',
            ],
            'user' => [
                'name' => 'X',
                'email' => 'admin@x.com',
                'password' => '12345678',
            ],
        ])->assertForbidden();
    }
}
