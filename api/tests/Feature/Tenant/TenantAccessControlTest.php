<?php

namespace Tests\Feature\Tenant;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\ApiToken\Services\ApiTokenService;
use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Exceptions\TenantInactive;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantAccessControlTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_users_of_inactive_tenant_are_blocked(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['is_active' => false]);

        Sanctum::actingAs($this->createAdmin($child));

        $this->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonStructure(['errors' => [TenantInactive::ERROR_KEY]]);

        $this->getJson('/api/clients')->assertForbidden();
    }

    public function test_users_of_inactive_tenant_can_still_log_out(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['is_active' => false]);

        Sanctum::actingAs($this->createAdmin($child));

        $this->postJson('/api/auth/logout')->assertOk();
    }

    public function test_login_is_denied_for_inactive_tenant(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['is_active' => false]);
        $this->createAdmin($child, ['email' => 'admin@inativa.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@inativa.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', TenantInactive::make()->getMessage());
    }

    public function test_reactivated_tenant_regains_access(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['is_active' => false]);
        $child->update(['is_active' => true]);

        Sanctum::actingAs($this->createAdmin($child));

        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_master_cannot_operate_inactive_child_and_does_not_list_it(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $active = $this->createChildTenant($umbrella, ['name' => 'Ativa']);
        $inactive = $this->createChildTenant($umbrella, ['name' => 'Inativa', 'is_active' => false]);

        Sanctum::actingAs($this->createMaster($umbrella));

        $available = collect($this->getJson('/api/auth/me')->assertOk()->json('data.available_tenants'))
            ->pluck('id');

        $this->assertTrue($available->contains($active->uuid));
        $this->assertFalse($available->contains($inactive->uuid));

        $this->postJson('/api/auth/select-tenant', ['tenant_id' => $inactive->uuid])->assertForbidden();
        $this->getJson('/api/clients', ['X-Tenant-Id' => $inactive->uuid])->assertForbidden();

        // A empresa inativa segue gerenciável pelo umbrella.
        $this->getJson("/api/tenant/children/{$inactive->uuid}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_umbrella_is_never_considered_inactive(): void
    {
        $umbrella = $this->createTenantWithRoles(['is_active' => false]);

        Sanctum::actingAs($this->createMaster($umbrella));

        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_permissions_of_disabled_modules_are_removed(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => [TenantModule::CRM->value]]);

        Sanctum::actingAs($this->createAdmin($child));

        $permissions = $this->getJson('/api/auth/me')->assertOk()->json('data.permissions');

        $this->assertContains(Permission::CLIENT_READ->value, $permissions);
        $this->assertContains(Permission::USER_READ->value, $permissions);
        $this->assertContains(Permission::CRM_LEAD_VIEW->value, $permissions);
        $this->assertNotContains(Permission::SERVICE_ORDER_READ->value, $permissions);
        $this->assertNotContains(Permission::TRACKING_READ->value, $permissions);
        $this->assertNotContains(Permission::VEHICLE_READ->value, $permissions);
        $this->assertNotContains(Permission::FINANCE_BILLING_READ->value, $permissions);

        $available = $this->getJson('/api/auth/me')->json('data.available_permissions');
        $this->assertContains(Permission::CRM_LEAD_VIEW->value, $available);
        $this->assertContains(Permission::USER_READ->value, $available);
        $this->assertNotContains(Permission::SERVICE_ORDER_READ->value, $available);

        $this->getJson('/api/clients')->assertOk();
        $this->getJson('/api/service-orders')->assertForbidden();
        $this->getJson('/api/services')->assertForbidden();
        $this->getJson('/api/checklists')->assertForbidden();
        $this->getJson('/api/vehicles')->assertForbidden();
        $this->getJson('/api/finance/billings')->assertForbidden();
    }

    public function test_shared_registrations_follow_any_module_that_provides_them(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => [TenantModule::SERVICE_ORDERS->value]]);

        Sanctum::actingAs($this->createAdmin($child));

        $this->getJson('/api/service-orders')->assertOk();
        $this->getJson('/api/services')->assertOk();
        $this->getJson('/api/checklists')->assertOk();
        $this->getJson('/api/vehicles')->assertOk();
        $this->getJson('/api/equipments')->assertOk();
        $this->getJson('/api/drivers')->assertForbidden();
        $this->getJson('/api/tracking/live')->assertForbidden();
    }

    public function test_master_permissions_follow_modules_of_selected_child(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => [TenantModule::FINANCE->value]]);

        Sanctum::actingAs($this->createMaster($umbrella));

        $headers = ['X-Tenant-Id' => $child->uuid];

        $permissions = $this->getJson('/api/auth/me', $headers)->assertOk()->json('data.permissions');

        $this->assertContains(Permission::FINANCE_BILLING_READ->value, $permissions);
        $this->assertNotContains(Permission::SERVICE_ORDER_READ->value, $permissions);
        $this->assertSame([TenantModule::FINANCE->value], $this->getJson('/api/auth/me', $headers)->json('data.tenant.modules'));

        $this->getJson('/api/service-orders', $headers)->assertForbidden();

        // No próprio umbrella, todos os módulos valem.
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.modules', TenantModule::values());
    }

    public function test_umbrella_roles_only_offer_group_administration_permissions(): void
    {
        $umbrella = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMaster($umbrella));

        $available = $this->getJson('/api/auth/me')->assertOk()->json('data.available_permissions');

        $this->assertContains(Permission::TENANT_CREATE->value, $available);
        $this->assertContains(Permission::ROLE_UPDATE->value, $available);
        $this->assertNotContains(Permission::CLIENT_READ->value, $available);
        $this->assertNotContains(Permission::SERVICE_ORDER_READ->value, $available);
    }

    public function test_master_operates_child_regardless_of_umbrella_role(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => [TenantModule::SERVICE_ORDERS->value]]);

        $role = Role::factory()->for($umbrella)->create(['name' => 'Gestor do grupo']);
        $role->syncPermissions([Permission::PANEL_WEB_ACCESS, Permission::TENANT_READ]);

        $master = User::factory()->for($umbrella)->master()->create();
        $master->assignRole($role);

        Sanctum::actingAs($master);

        $headers = ['X-Tenant-Id' => $child->uuid];

        $this->getJson('/api/service-orders', $headers)->assertOk();
        $this->getJson('/api/clients', $headers)->assertOk();
        $this->getJson('/api/finance/billings', $headers)->assertForbidden();
    }

    public function test_client_tabs_require_their_module(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => [TenantModule::TRACKING->value]]);
        $client = Client::factory()->for($child)->create();

        Sanctum::actingAs($this->createAdmin($child));

        $this->getJson("/api/clients/{$client->uuid}")->assertOk();
        $this->getJson("/api/clients/{$client->uuid}/shared-vehicles")->assertOk();
        $this->getJson("/api/clients/{$client->uuid}/contract")
            ->assertForbidden()
            ->assertJsonPath('message', 'Este módulo não está habilitado para a sua empresa.');
        $this->getJson("/api/clients/{$client->uuid}/order")->assertForbidden();
    }

    public function test_scheduling_panel_requires_service_orders_module(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => [TenantModule::TRACKING->value]]);
        $this->createAdmin($child, ['email' => 'admin@filial.com']);

        $this->withHeader('X-Panel', 'agendamento')
            ->postJson('/api/auth/login', [
                'email' => 'admin@filial.com',
                'password' => 'password',
            ])
            ->assertForbidden();
    }

    public function test_api_token_scope_is_limited_by_modules(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => [TenantModule::CRM->value]]);

        TenantContext::set($child);
        $plain = app(ApiTokenService::class)->issue('Integração')->plainTextToken;
        $this->forgetTenantContext();

        $this->getJson('/api/clients', ['Authorization' => "Bearer {$plain}"])->assertOk();
        $this->getJson('/api/service-orders', ['Authorization' => "Bearer {$plain}"])->assertForbidden();
    }

    public function test_role_permissions_stay_stored_when_module_is_disabled(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, ['modules' => []]);

        $role = Role::query()->forTenant($child)->where('name', DefaultRole::ADMINISTRATOR->value)->firstOrFail();

        // Reativar o módulo devolve o acesso sem precisar reconfigurar perfis.
        $this->assertTrue($role->hasPermission(Permission::SERVICE_ORDER_READ));

        $admin = User::query()->whereKey($this->createAdmin($child)->getKey())->firstOrFail();
        $this->assertFalse($admin->hasPermission(Permission::SERVICE_ORDER_READ));

        $child->update(['modules' => [TenantModule::SERVICE_ORDERS->value]]);
        $admin->refresh();

        $this->assertTrue($admin->hasPermission(Permission::SERVICE_ORDER_READ));
    }

    public function test_every_module_permission_exists_and_core_permissions_are_unmapped(): void
    {
        foreach (TenantModule::cases() as $module) {
            $this->assertNotEmpty($module->permissions());
        }

        $this->assertSame([], TenantModule::providing(Permission::CLIENT_READ));
        $this->assertSame([], TenantModule::providing(Permission::USER_READ));
        $this->assertSame([], TenantModule::providing(Permission::NOTIFICATION_READ));
        $this->assertContains(TenantModule::FINANCE, TenantModule::providing(Permission::SERVICE_READ));
        $this->assertContains(TenantModule::SERVICE_ORDERS, TenantModule::providing(Permission::SERVICE_READ));
    }

    public function test_new_tenant_defaults_to_all_modules(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertSame(TenantModule::values(), $tenant->modules);
    }
}
