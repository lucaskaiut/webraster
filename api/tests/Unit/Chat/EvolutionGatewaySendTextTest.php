<?php

namespace Tests\Unit\Chat;

use App\Modules\Chat\Gateways\EvolutionGateway;
use App\Modules\Chat\Models\MessagingConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvolutionGatewaySendTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_text_uses_evolution_v2_payload_shape(): void
    {
        Config::set('chat.evolution.base_url', 'https://evolution.test');
        Config::set('chat.evolution.api_key', 'test-key');

        Http::fake([
            'https://evolution.test/message/sendText/demo-instance' => Http::response([
                'key' => ['id' => 'MSG123'],
            ], 200),
        ]);

        $connection = MessagingConnection::query()->make([
            'provider' => 'evolution',
            'base_url' => 'https://evolution.test',
            'instance_name' => 'demo-instance',
            'credentials' => [],
        ]);

        $gateway = new EvolutionGateway;
        $result = $gateway->sendText($connection, '+55 11 99999-0000', 'Olá');

        $this->assertTrue($result->success);
        $this->assertSame('MSG123', $result->externalId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://evolution.test/message/sendText/demo-instance'
                && $request['number'] === '5511999990000'
                && $request['text'] === 'Olá'
                && ! array_key_exists('textMessage', $request->data());
        });
    }
}
