<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Chat\Support\EvolutionConfig;
use App\Modules\Chat\Support\MessagingGatewayResolver;
use Illuminate\Support\Collection;

final class MessagingConnectionService
{
    public function __construct(private readonly MessagingGatewayResolver $gateways) {}

    public function list(): Collection
    {
        return MessagingConnection::query()->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MessagingConnection
    {
        $this->gateways->assertActive((string) $data['provider']);

        $gateway = $this->gateways->resolve((string) $data['provider']);

        $baseUrl = $data['base_url'] ?? null;

        if ($data['provider'] === 'evolution') {
            $baseUrl = EvolutionConfig::baseUrl();
        }

        return MessagingConnection::query()->create([
            'provider' => $data['provider'],
            'name' => $data['name'],
            'base_url' => $baseUrl ?? config('chat.default_base_url'),
            'instance_name' => $data['instance_name'] ?? null,
            'credentials' => $data['credentials'] ?? [],
            'connection_status' => $data['connection_status'] ?? 'disconnected',
            'capabilities' => $gateway->capabilities(),
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MessagingConnection $connection, array $data): MessagingConnection
    {
        if (isset($data['credentials']) && is_array($data['credentials'])) {
            $merged = array_merge($connection->credentials ?? [], $data['credentials']);
            $data['credentials'] = $merged;
        }

        $connection->fill($data)->save();

        return $connection;
    }
}
