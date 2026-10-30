<?php

namespace Tests\Feature\Tenant;

use App\Modules\Billing\Http\Middleware\EnsureActiveSubscription;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
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

        $other = $this->createTenantWithRoles(['name' => 'Outro Grupo']);
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

        $child = Tenant::query()->where('email', 'contato@nova.com')->firstOrFail();

        $this->assertSame($umbrella->getKey(), $child->parent_id);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@nova.com',
            'tenant_id' => $child->getKey(),
            'is_master' => false,
        ]);
    }

    public function test_child_creation_ignores_plan_and_generates_no_subscription(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'Empresa Paga',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@paga.com',
                'phone' => '41999999999',
            ],
            'user' => [
                'name' => 'Admin Paga',
                'email' => 'admin@paga.com',
                'password' => '12345678',
            ],
            'plan_id' => $plan->uuid,
            'is_complimentary' => true,
        ])->assertCreated();

        $child = Tenant::query()->where('email', 'contato@paga.com')->firstOrFail();

        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $child->getKey()]);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_child_is_created_active_with_all_modules_by_default(): void
    {
        $umbrella = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'Empresa Padrão',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@padrao.com',
                'phone' => '41999999999',
            ],
            'user' => [
                'name' => 'Admin Padrão',
                'email' => 'admin@padrao.com',
                'password' => '12345678',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.tenant.is_active', true)
            ->assertJsonPath('data.tenant.modules', TenantModule::values());
    }

    public function test_master_creates_child_with_selected_modules_and_their_requirements(): void
    {
        $umbrella = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', [
            'tenant' => [
                'name' => 'Empresa OS',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@os.com',
                'phone' => '41999999999',
                'modules' => ['service_orders', 'alerts'],
            ],
            'user' => [
                'name' => 'Admin OS',
                'email' => 'admin@os.com',
                'password' => '12345678',
            ],
        ])
            ->assertCreated()
            // Alertas exigem o rastreamento, incluído automaticamente.
            ->assertJsonPath('data.tenant.modules', ['tracking', 'alerts', 'service_orders']);
    }

    public function test_master_updates_child_status_and_modules(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->putJson("/api/tenant/children/{$child->uuid}", [
            'tenant' => [
                'name' => $child->name,
                'document' => $child->document,
                'email' => $child->email,
                'phone' => $child->phone,
                'is_active' => false,
                'modules' => ['finance'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.modules', ['finance']);

        $child->refresh();
        $this->assertFalse($child->is_active);
        $this->assertSame(['finance'], $child->modules);
    }

    public function test_unknown_module_is_rejected(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->putJson("/api/tenant/children/{$child->uuid}", [
            'tenant' => [
                'name' => $child->name,
                'document' => $child->document,
                'email' => $child->email,
                'phone' => $child->phone,
                'modules' => ['inexistente'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant.modules.0']);
    }

    public function test_child_admin_cannot_change_own_status_or_modules(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => ['finance']]);

        Sanctum::actingAs($this->createAdmin($child));

        $this->putJson('/api/tenant', [
            'name' => 'Nome Novo',
            'is_active' => false,
            'modules' => TenantModule::values(),
        ])->assertOk();

        $child->refresh();
        $this->assertSame('Nome Novo', $child->name);
        $this->assertTrue($child->is_active);
        $this->assertSame(['finance'], $child->modules);
    }

    public function test_master_gets_overview_of_child_tenants(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $active = $this->createChildTenant($umbrella);
        $this->createChildTenant($umbrella, ['is_active' => false]);
        $this->createAdmin($active);

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->getJson('/api/tenant/overview')
            ->assertOk()
            ->assertJsonPath('data.companies.total', 2)
            ->assertJsonPath('data.companies.active', 1)
            ->assertJsonPath('data.companies.inactive', 1)
            ->assertJsonPath('data.users', 1)
            ->assertJsonCount(2, 'data.recent');
    }

    public function test_child_admin_cannot_get_overview(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);

        Sanctum::actingAs($this->createAdmin($child));

        $this->getJson('/api/tenant/overview')->assertForbidden();
    }

    public function test_master_lists_module_catalog(): void
    {
        $umbrella = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->getJson('/api/tenant/modules')
            ->assertOk()
            ->assertJsonCount(count(TenantModule::cases()), 'data')
            ->assertJsonPath('data.0.key', 'tracking')
            ->assertJsonPath('data.1.requires', ['tracking']);
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
            ],
            'user' => [
                'name' => 'X',
                'email' => 'admin@x.com',
                'password' => '12345678',
            ],
        ])->assertForbidden();
    }

    public function test_master_can_show_and_update_child(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa Edit',
            'email' => 'contato@edit.com.br',
        ]);

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->getJson("/api/tenant/children/{$child->uuid}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Edit');

        $this->putJson("/api/tenant/children/{$child->uuid}", [
            'tenant' => [
                'name' => 'Empresa Editada',
                'document' => $child->document,
                'email' => $child->email,
                'phone' => $child->phone,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Editada');
    }

    public function test_master_cannot_manage_foreign_child(): void
    {
        $umbrellaA = $this->createTenantWithRoles(['email' => 'a@a.com']);
        $umbrellaB = $this->createTenantWithRoles(['email' => 'b@b.com']);
        $foreignChild = $this->createChildTenant($umbrellaB);

        Sanctum::actingAs($this->createMaster($umbrellaA));

        $this->getJson("/api/tenant/children/{$foreignChild->uuid}")->assertForbidden();

        $this->putJson("/api/tenant/children/{$foreignChild->uuid}", [
            'tenant' => [
                'name' => 'Hack',
                'document' => $foreignChild->document,
                'email' => $foreignChild->email,
                'phone' => $foreignChild->phone,
            ],
        ])->assertForbidden();
    }

    public function test_master_can_manage_child_while_child_context_is_selected(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa Contexto',
        ]);
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
            ],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Contexto Editada');
    }

    public function test_subscription_middleware_is_disabled(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);

        TenantContext::set($child);

        $middleware = app(EnsureActiveSubscription::class);
        $result = $middleware->handle(
            request(),
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(200, $result->getStatusCode());
    }
}
