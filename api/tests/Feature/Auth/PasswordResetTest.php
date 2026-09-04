<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_forgot_password_sends_notification_for_existing_email(): void
    {
        Notification::fake();

        $tenant = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'admin@empresa.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'inexistente@empresa.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertNothingSent();
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'password',
        ])->json('data.token');

        $this->assertNotEmpty($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'admin@empresa.com',
            'token' => $resetToken,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();

        $this->assertTrue(Hash::check('nova-senha-segura', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'nova-senha-segura',
        ])->assertOk();
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'admin@empresa.com',
            'token' => 'token-invalido',
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_notification_contains_frontend_url(): void
    {
        config(['app.frontend_url' => 'http://frontend.test']);

        $tenant = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $notification = new ResetPasswordNotification('reset-token-123');
        $mail = $notification->toMail($user);

        $this->assertStringContainsString(
            'http://frontend.test/auth/reset-password?token=reset-token-123&email=admin%40empresa.com',
            $mail->actionUrl,
        );
    }
}
