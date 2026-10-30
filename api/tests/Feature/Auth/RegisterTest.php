<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_closed(): void
    {
        $this->postJson('/api/auth/register', [
            'tenant' => [
                'name' => 'Empresa Exemplo',
                'document' => '11.222.333/0001-81',
                'email' => 'contato@empresa.com',
                'phone' => '41999999999',
            ],
            'user' => [
                'name' => 'Administrador',
                'email' => 'admin@empresa.com',
                'password' => '12345678',
            ],
        ])->assertNotFound();

        $this->assertDatabaseCount('tenants', 0);
    }
}
