<?php

namespace App\Integrations\Traccar;

use App\Integrations\Traccar\DTOs\TraccarCommandResult;
use App\Integrations\Traccar\DTOs\TraccarCommandType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Descoberta e envio de comandos 100% dinâmicos via API Traccar.
 * Fonte da verdade: GET /api/commands/types?deviceId={id}
 */
class TraccarCommandService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly TraccarClient $client) {}

    /**
     * @return Collection<int, TraccarCommandType>
     */
    public function listTypes(int $traccarDeviceId, bool $fresh = false): Collection
    {
        $cacheKey = $this->cacheKey($traccarDeviceId);

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        /** @var list<array<string, mixed>> $cached */
        $cached = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($traccarDeviceId) {
            return $this->fetchTypesFromTraccar($traccarDeviceId);
        });

        return collect($cached)
            ->map(fn (array $item) => TraccarCommandType::fromArray($item))
            ->filter(fn (TraccarCommandType $type) => $type->type !== '')
            ->values();
    }

    public function supports(int $traccarDeviceId, string $commandType, bool $fresh = true): bool
    {
        return $this->listTypes($traccarDeviceId, $fresh)
            ->contains(fn (TraccarCommandType $type) => $type->type === $commandType);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function send(int $traccarDeviceId, string $type, array $attributes = []): TraccarCommandResult
    {
        $payload = [
            'deviceId' => $traccarDeviceId,
            'type' => $type,
            'attributes' => (object) $attributes,
        ];

        $response = $this->client->post('/api/commands/send', $payload);

        $body = $response->json();

        return new TraccarCommandResult(
            successful: $response->successful(),
            status: $response->status(),
            body: is_array($body) ? $body : null,
            rawBody: $response->body(),
        );
    }

    public function forgetCache(int $traccarDeviceId): void
    {
        Cache::forget($this->cacheKey($traccarDeviceId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTypesFromTraccar(int $traccarDeviceId): array
    {
        $response = $this->client->get('/api/commands/types', [
            'deviceId' => $traccarDeviceId,
        ]);

        if (! $response->successful()) {
            $this->client->fail(
                'Falha ao consultar tipos de comando no Traccar.',
                $response->status(),
                $response->body(),
            );
        }

        $json = $response->json() ?? [];

        if (! is_array($json)) {
            return [];
        }

        /** @var list<array<string, mixed>> $items */
        $items = [];

        foreach ($json as $item) {
            if (is_string($item)) {
                $items[] = ['type' => $item];

                continue;
            }

            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function cacheKey(int $traccarDeviceId): string
    {
        return "traccar:command-types:{$traccarDeviceId}";
    }
}
