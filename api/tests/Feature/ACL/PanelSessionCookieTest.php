<?php

namespace Tests\Feature\ACL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PanelSessionCookieTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Driver persistente: o "array" perde os dados ao recriar a sessão entre requisições.
        config([
            'sanctum.stateful' => ['localhost'],
            'session.driver' => 'database',
        ]);
    }

    public function test_csrf_cookie_uses_panel_specific_cookie_names(): void
    {
        $session = config('session.cookie');

        $this->withHeader('X-Panel', 'web')
            ->get('/sanctum/csrf-cookie')
            ->assertCookie('XSRF-TOKEN-web')
            ->assertCookie("{$session}-web")
            ->assertCookieMissing('XSRF-TOKEN')
            ->assertCookieMissing($session);

        $this->resetSessionState();

        $this->withHeader('X-Panel', 'agendamento')
            ->get('/sanctum/csrf-cookie')
            ->assertCookie('XSRF-TOKEN-agendamento')
            ->assertCookie("{$session}-agendamento");
    }

    public function test_requests_without_panel_header_keep_default_cookie_names(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertCookie('XSRF-TOKEN')
            ->assertCookie(config('session.cookie'));
    }

    public function test_session_from_one_panel_is_not_used_by_the_other(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $login = $this->statefulRequest('agendamento')
            ->postJson('/api/auth/login', [
                'email' => 'admin@empresa.com',
                'password' => 'password',
            ])
            ->assertOk();

        $cookieName = config('session.cookie').'-agendamento';
        $sessionId = $this->sessionIdFrom($login, $cookieName);

        $this->resetSessionState();

        $this->statefulRequest('agendamento')
            ->withCookie($cookieName, $sessionId)
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->resetSessionState();

        // O navegador envia todos os cookies do domínio da API, inclusive o do outro painel.
        $this->statefulRequest('web')
            ->withCookie($cookieName, $sessionId)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    private function statefulRequest(string $panel): static
    {
        return $this->withCredentials()->withHeaders([
            'X-Panel' => $panel,
            'Referer' => 'http://localhost/',
        ]);
    }

    private function sessionIdFrom(TestResponse $response, string $cookieName): string
    {
        $cookie = $response->getCookie($cookieName);
        $this->assertNotNull($cookie, "Cookie [{$cookieName}] não foi emitido.");

        return (string) $cookie->getValue();
    }

    /**
     * Entre requisições do mesmo teste, o guard e a sessão ficam em memória;
     * recriá-los simula requisições independentes do navegador.
     */
    private function resetSessionState(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->flushHeaders();
        $this->defaultCookies = [];
    }
}
