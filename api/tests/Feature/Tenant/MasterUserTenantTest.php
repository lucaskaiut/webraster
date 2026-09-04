<?php

namespace Tests\Feature\Tenant;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Tenant\Resolution\Strategies\AuthenticatedUserStrategy;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MasterUserTenantTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'tenant'])->get('/api/testing/current-tenant', function () {
            return response()->json([
                'resolved' => TenantContext::isResolved(),
                'tenant_id' => TenantContext::tenantId(),
                'tenant_uuid' => TenantContext::tenant()?->uuid,
            ]);
        });
    }

    public function test_regular_user_ignores_x_tenant_id_header(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles();
        $user = $this->createMember($tenantA);

        Sanctum::actingAs($user);

        $this->getJson('/api/testing/current-tenant', [
            AuthenticatedUserStrategy::HEADER => $tenantB->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('tenant_uuid', $tenantA->uuid);
    }

    public function test_regular_user_cannot_select_tenant(): void
    {
        $tenant = $this->createTenantWithRoles();
        $child = $this->createChildTenant($tenant);
        $user = $this->createAdmin($tenant);

        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');

        $this->postJson('/api/auth/select-tenant', [
            'tenant_id' => $child->uuid,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }

    public function test_master_login_returns_available_child_tenants(): void
    {
        $umbrella = $this->createTenantWithRoles(['name' => 'Grupo XPTO']);
        $childA = $this->createChildTenant($umbrella, ['name' => 'Empresa A']);
        $childB = $this->createChildTenant($umbrella, ['name' => 'Empresa B']);
        $other = $this->createTenantWithRoles(['name' => 'Outro Grupo']);
        $this->createChildTenant($other, ['name' => 'Empresa Z']);

        $master = $this->createMaster($umbrella, ['email' => 'master@grupo.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'master@grupo.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_master', true)
            ->assertJsonPath('data.user.is_master', true);

        $available = collect($response->json('data.available_tenants'));

        $this->assertSame($umbrella->uuid, $available->first()['id']);
        $this->assertTrue($available->first()['is_home']);
        $this->assertEqualsCanonicalizing(
            [$umbrella->uuid, $childA->uuid, $childB->uuid],
            $available->pluck('id')->all(),
        );
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $master->getKey(),
            'action' => AuditAction::MasterLogin->value,
        ]);
    }

    public function test_master_can_select_home_tenant(): void
    {
        $umbrella = $this->createTenantWithRoles(['name' => 'Grupo']);
        $this->createChildTenant($umbrella, ['name' => 'Filial']);
        $master = $this->createMaster($umbrella, ['email' => 'master@grupo.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'master@grupo.com',
            'password' => 'password',
        ])->json('data.token');

        $this->postJson('/api/auth/select-tenant', [
            'tenant_id' => $umbrella->uuid,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $umbrella->uuid)
            ->assertJsonPath('data.tenant.is_home', true);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $master->getKey(),
            'selected_tenant_id' => $umbrella->getKey(),
            'action' => AuditAction::TenantSwitched->value,
        ]);
    }

    public function test_master_can_resolve_authorized_child_via_header(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $master = $this->createMaster($umbrella);

        Sanctum::actingAs($master);
        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant', [
            AuthenticatedUserStrategy::HEADER => $child->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('tenant_uuid', $child->uuid);
    }

    public function test_master_cannot_access_tenant_from_another_group(): void
    {
        $umbrellaA = $this->createTenantWithRoles();
        $this->createChildTenant($umbrellaA);

        $umbrellaB = $this->createTenantWithRoles();
        $foreignChild = $this->createChildTenant($umbrellaB);

        $master = $this->createMaster($umbrellaA);

        Sanctum::actingAs($master);
        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant', [
            AuthenticatedUserStrategy::HEADER => $foreignChild->uuid,
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Acesso ao tenant informado não é permitido.');
    }

    public function test_master_gets_403_for_unknown_tenant_header(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $master = $this->createMaster($umbrella);

        Sanctum::actingAs($master);
        $this->forgetTenantContext();

        $this->getJson('/api/testing/current-tenant', [
            AuthenticatedUserStrategy::HEADER => '00000000-0000-0000-0000-000000000099',
        ])
            ->assertForbidden();
    }

    public function test_master_can_switch_tenant_and_audit_is_recorded(): void
    {
        [$umbrella, $childA] = $this->createOperationalChild(childAttributes: ['name' => 'Empresa A']);
        [, $childB] = $this->createOperationalChild($umbrella, ['name' => 'Empresa B']);
        $master = $this->createMaster($umbrella, ['email' => 'master@grupo.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'master@grupo.com',
            'password' => 'password',
        ])->json('data.token');

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/api/auth/select-tenant', [
            'tenant_id' => $childB->uuid,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $childB->uuid);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $master->getKey(),
            'selected_tenant_id' => $childB->getKey(),
            'action' => AuditAction::TenantSwitched->value,
        ]);

        $this->forgetTenantContext();

        $this->getJson('/api/users', [
            ...$headers,
            AuthenticatedUserStrategy::HEADER => $childA->uuid,
        ])->assertOk();
    }

    public function test_master_operates_on_selected_child_data_isolation(): void
    {
        [$umbrella, $childA] = $this->createOperationalChild();
        [, $childB] = $this->createOperationalChild($umbrella);

        $userA = $this->createMember($childA, ['email' => 'a@empresa.com', 'name' => 'User A']);
        $this->createMember($childB, ['email' => 'b@empresa.com', 'name' => 'User B']);

        $master = $this->createMaster($umbrella, ['email' => 'master@grupo.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'master@grupo.com',
            'password' => 'password',
        ])->json('data.token');

        $this->forgetTenantContext();

        $response = $this->getJson('/api/users', [
            'Authorization' => "Bearer {$token}",
            AuthenticatedUserStrategy::HEADER => $childA->uuid,
        ]);

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();

        $this->assertContains($userA->email, $emails);
        $this->assertNotContains('b@empresa.com', $emails);
        $this->assertNotContains($master->email, $emails);
    }

    public function test_me_includes_master_flags_and_available_tenants(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['name' => 'Filial']);
        $master = $this->createMaster($umbrella, ['email' => 'master@grupo.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'master@grupo.com',
            'password' => 'password',
        ])->json('data.token');

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.is_master', true)
            ->assertJsonPath('data.available_tenants.0.id', $umbrella->uuid)
            ->assertJsonPath('data.available_tenants.0.is_home', true)
            ->assertJsonPath('data.available_tenants.1.id', $child->uuid)
            ->assertJsonPath('data.user.email', $master->email);
    }
}
