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

    public function test_master_creates_child_with_complimentary_access(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'Parceira',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@parceira.com',
                'phone' => '41999999999',
                'domain' => 'parceira.com.br',
            ],
            'user' => [
                'name' => 'Admin Parceira',
                'email' => 'admin@parceira.com',
                'password' => '12345678',
            ],
            'plan_id' => $plan->uuid,
            'is_complimentary' => true,
            'complimentary_ends_at' => null,
        ])
            ->assertCreated()
            ->assertJsonPath('data.tenant.subscription.is_complimentary', true)
            ->assertJsonPath('data.tenant.subscription.is_complimentary_active', true);

        $child = Tenant::query()->where('domain', 'parceira.com.br')->firstOrFail();

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $child->getKey(),
            'plan_id' => $plan->getKey(),
            'is_complimentary' => true,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('subscription_events', [
            'event' => 'COMPLIMENTARY_GRANTED',
        ]);
    }

    public function test_complimentary_requires_plan_id(): void
    {
        $umbrella = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'Parceira',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@parceira.com',
                'phone' => '41999999999',
                'domain' => 'parceira.com.br',
            ],
            'user' => [
                'name' => 'Admin Parceira',
                'email' => 'admin@parceira.com',
                'password' => '12345678',
            ],
            'is_complimentary' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_master_can_show_and_update_child(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa Edit',
            'domain' => 'edit.com.br',
            'email' => 'contato@edit.com.br',
        ]);

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->getJson("/api/tenant/children/{$child->uuid}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Edit')
            ->assertJsonPath('data.subscription', null);

        $this->putJson("/api/tenant/children/{$child->uuid}", [
            'tenant' => [
                'name' => 'Empresa Editada',
                'document' => $child->document,
                'email' => $child->email,
                'phone' => $child->phone,
                'domain' => $child->domain,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Editada')
            ->assertJsonPath('data.subscription', null);
    }

    public function test_master_cannot_manage_foreign_child(): void
    {
        $umbrellaA = $this->createTenantWithRoles(['domain' => 'a.com.br', 'email' => 'a@a.com']);
        $umbrellaB = $this->createTenantWithRoles(['domain' => 'b.com.br', 'email' => 'b@b.com']);
        $foreignChild = $this->createChildTenant($umbrellaB, ['domain' => 'filho-b.com.br']);
        $plan = Plan::factory()->forTenant($umbrellaA)->create();

        Sanctum::actingAs($this->createMaster($umbrellaA));

        $this->getJson("/api/tenant/children/{$foreignChild->uuid}")->assertForbidden();

        $this->putJson("/api/tenant/children/{$foreignChild->uuid}", [
            'tenant' => [
                'name' => 'Hack',
                'document' => $foreignChild->document,
                'email' => $foreignChild->email,
                'phone' => $foreignChild->phone,
                'domain' => $foreignChild->domain,
            ],
            'plan_id' => $plan->uuid,
            'is_complimentary' => true,
        ])->assertForbidden();
    }

    public function test_master_can_manage_child_while_child_context_is_selected(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa Contexto',
            'domain' => 'contexto.com.br',
        ]);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();

        app(\App\Modules\Billing\Services\SubscriptionService::class)
            ->grantComplimentary($child, $plan);

        Sanctum::actingAs($this->createMaster($umbrella));

        $headers = ['X-Tenant-Id' => $child->uuid];

        $this->getJson('/api/tenant/children', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/tenant/children/{$child->uuid}", $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Contexto');

        $this->putJson("/api/tenant/children/{$child->uuid}", [
            'tenant' => [
                'name' => 'Empresa Contexto Editada',
                'document' => $child->document,
                'email' => $child->email,
                'phone' => $child->phone,
                'domain' => $child->domain,
            ],
            'plan_id' => $plan->uuid,
            'is_complimentary' => true,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Contexto Editada');
    }

    public function test_subscription_middleware_is_disabled(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);

        \App\Modules\Tenant\Support\Facades\TenantContext::set($child);

        $middleware = app(\App\Modules\Billing\Http\Middleware\EnsureActiveSubscription::class);
        $result = $middleware->handle(
            request(),
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(200, $result->getStatusCode());
    }
}
