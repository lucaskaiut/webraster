<?php

namespace Tests\Feature\ServiceOrder;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceOrderContactWhatsappTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_scheduling_panel_requires_contact_phone(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->withHeader('X-Panel', 'agendamento')
            ->postJson('/api/service-orders', ['client_id' => $client->uuid])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_phone');

        $this->withHeader('X-Panel', 'agendamento')
            ->postJson('/api/service-orders', [
                'client_id' => $client->uuid,
                'contact_phone' => '(41) 99999-8888',
            ])
            ->assertCreated()
            ->assertJsonPath('data.contact_phone', '41999998888');
    }

    public function test_web_panel_keeps_contact_phone_optional(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->withHeader('X-Panel', 'web')
            ->postJson('/api/service-orders', ['client_id' => $client->uuid])
            ->assertCreated()
            ->assertJsonPath('data.contact_phone', null);
    }

    public function test_scheduling_panel_cannot_clear_contact_phone_on_update(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $order = ServiceOrder::factory()->forClient($client)->create(['contact_phone' => '41999998888']);
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->withHeader('X-Panel', 'agendamento')
            ->putJson("/api/service-orders/{$order->uuid}", ['contact_phone' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_phone');
    }

    public function test_technician_sees_contact_phone_and_saves_greeting(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));
        $order = ServiceOrder::factory()->forClient($client)->create([
            'technician_id' => $technician->getKey(),
            'contact_phone' => '41999998888',
        ]);

        Sanctum::actingAs($technician);

        $this->getJson("/api/technician/service-orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonPath('data.contact_phone', '41999998888');

        $this->putJson('/api/auth/service-order-greeting', [
            'service_order_greeting' => '  Olá, tudo certo para o serviço de hoje?  ',
        ])
            ->assertOk()
            ->assertJsonPath('data.service_order_greeting', 'Olá, tudo certo para o serviço de hoje?');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.service_order_greeting', 'Olá, tudo certo para o serviço de hoje?');

        $this->putJson('/api/auth/service-order-greeting', ['service_order_greeting' => ''])
            ->assertOk()
            ->assertJsonPath('data.service_order_greeting', null);
    }
}
