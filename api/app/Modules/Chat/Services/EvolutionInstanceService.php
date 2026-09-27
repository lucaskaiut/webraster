<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\DTOs\EvolutionConnectResult;
use App\Modules\Chat\Gateways\EvolutionGateway;
use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Chat\Support\EvolutionConfig;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class EvolutionInstanceService
{
    public function __construct(
        private readonly MessagingConnectionService $connections,
        private readonly EvolutionGateway $evolution,
    ) {}

    /**
     * @param  array{name?: string}  $data
     * @return array{connection: MessagingConnection, connect: EvolutionConnectResult}
     */
    public function bootstrap(array $data = []): array
    {
        EvolutionConfig::assertConfigured();

        $tenant = TenantContext::tenant();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant não resolvido.'],
            ]);
        }

        $instanceName = $this->generateInstanceName($tenant->uuid);

        $connection = $this->connections->create([
            'provider' => 'evolution',
            'name' => $data['name'] ?? 'WhatsApp',
            'base_url' => EvolutionConfig::baseUrl(),
            'instance_name' => $instanceName,
            'credentials' => [],
            'connection_status' => 'connecting',
        ]);

        $this->provisionInstance($connection);

        $connect = $this->connect($connection);

        return [
            'connection' => $connection->fresh(),
            'connect' => $connect,
        ];
    }

    public function provisionInstance(MessagingConnection $connection): void
    {
        $this->assertEvolution($connection);
        EvolutionConfig::assertConfigured();

        $instanceName = (string) $connection->instance_name;

        if ($instanceName === '') {
            throw ValidationException::withMessages([
                'instance_name' => ['Instância não definida para esta conexão.'],
            ]);
        }

        try {
            $this->evolution->createWhatsappInstance(
                $connection,
                $instanceName,
                $connection->webhookUrl(),
            );
        } catch (RuntimeException $exception) {
            Log::info('chat.evolution.instance_create_skipped', [
                'connection_id' => $connection->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function connect(MessagingConnection $connection): EvolutionConnectResult
    {
        $this->assertEvolution($connection);
        EvolutionConfig::assertConfigured();

        $payload = $this->evolution->connectWhatsapp($connection);
        $result = $this->mapConnectPayload($payload);

        $connection->forceFill([
            'connection_status' => $this->normalizeState($result->state),
        ])->save();

        return $result;
    }

    public function syncState(MessagingConnection $connection): EvolutionConnectResult
    {
        $this->assertEvolution($connection);
        EvolutionConfig::assertConfigured();

        $payload = $this->evolution->fetchConnectionState($connection);
        $state = $this->extractState($payload);

        $connection->forceFill([
            'connection_status' => $this->normalizeState($state),
        ])->save();

        if ($state === 'open') {
            return new EvolutionConnectResult(state: 'open');
        }

        return $this->connect($connection);
    }

    private function generateInstanceName(string $tenantUuid): string
    {
        $suffix = Str::lower(str_replace('-', '', $tenantUuid));

        return 'crm-'.Str::substr($suffix, 0, 24);
    }

    private function assertEvolution(MessagingConnection $connection): void
    {
        if ($connection->provider !== 'evolution') {
            throw ValidationException::withMessages([
                'provider' => ['Este fluxo está disponível apenas para Evolution API.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapConnectPayload(array $payload): EvolutionConnectResult
    {
        $state = $this->extractState($payload);
        $base64 = $this->extractQrBase64($payload);
        $pairingCode = data_get($payload, 'pairingCode');

        if (! is_string($pairingCode)) {
            $pairingCode = data_get($payload, 'qrcode.pairingCode');
        }

        $count = data_get($payload, 'count');
        if ($count === null) {
            $count = data_get($payload, 'qrcode.count');
        }

        return new EvolutionConnectResult(
            state: $state,
            qrcodeBase64: is_string($base64) ? $base64 : null,
            pairingCode: is_string($pairingCode) ? $pairingCode : null,
            count: is_numeric($count) ? (int) $count : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractState(array $payload): string
    {
        $state = data_get($payload, 'instance.state')
            ?? data_get($payload, 'state')
            ?? data_get($payload, 'instance.status')
            ?? data_get($payload, 'status');

        if (! is_string($state) || $state === '') {
            return 'connecting';
        }

        return strtolower($state);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractQrBase64(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'base64'),
            data_get($payload, 'qrcode.base64'),
            data_get($payload, 'qrcode'),
            data_get($payload, 'instance.qrcode.base64'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if (str_starts_with($candidate, 'data:image')) {
                return $candidate;
            }

            if (strlen($candidate) > 100) {
                return 'data:image/png;base64,'.$candidate;
            }
        }

        return null;
    }

    private function normalizeState(string $state): string
    {
        return match ($state) {
            'open', 'connected' => 'connected',
            'connecting' => 'connecting',
            default => 'disconnected',
        };
    }
}
