<?php

namespace Tests\Feature\Tenant;

use App\Modules\ACL\Enums\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantThemeTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_admin_updates_theme_and_session_exposes_it(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant/theme', [
            'primary_color' => '#1e3a8a',
            'secondary_color' => '#D97706',
            'font_family' => 'Poppins',
        ])
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#1E3A8A')
            ->assertJsonPath('data.secondary_color', '#D97706')
            ->assertJsonPath('data.font_family', 'Poppins');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.theme.primary_color', '#1E3A8A')
            ->assertJsonPath('data.tenant.theme.font_family', 'Poppins');
    }

    public function test_reset_to_default_clears_theme(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $tenant->forceFill(['theme' => ['primary_color' => '#111111']])->save();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant/theme', [
            'primary_color' => null,
            'secondary_color' => null,
            'font_family' => null,
        ])->assertOk()->assertJsonPath('data.primary_color', null);

        $this->assertNull($tenant->fresh()->theme);
    }

    public function test_validates_colors_and_font(): void
    {
        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson('/api/tenant/theme', [
            'primary_color' => 'blue',
            'secondary_color' => '#12345',
            'font_family' => 'Comic Sans',
        ])->assertUnprocessable()->assertJsonValidationErrors(['primary_color', 'secondary_color', 'font_family']);
    }

    public function test_requires_theme_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $member = $this->createMember($tenant);
        $this->assertFalse($member->hasPermission(Permission::THEME_MANAGE));
        Sanctum::actingAs($member);

        $this->putJson('/api/tenant/theme', [
            'primary_color' => '#1E3A8A',
            'secondary_color' => null,
            'font_family' => null,
        ])->assertForbidden();

        $this->getJson('/api/tenant/theme')->assertForbidden();
    }
}
