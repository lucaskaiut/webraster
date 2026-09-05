<?php

namespace App\Modules\Tracking\Gateways;

use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\DTOs\TraccarDevice;
use App\Modules\Tracking\DTOs\TraccarPosition;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HttpTraccarGateway implements TraccarGateway
{
    public function isConfigured(): bool
    {
        if (! config('traccar.enabled')) {
            return false;
        }

        if (blank(config('traccar.base_url'))) {
            return false;
        }

        return filled(config('traccar.token'))
            || (filled(config('traccar.email')) && filled(config('traccar.password')));
    }

    public function listDevices(): Collection
    {
        $response = $this->client()->get('/api/devices');

        if (! $response->successful()) {
            $this->fail('Falha ao listar dispositivos no Traccar.', $response->status(), $response->body());
        }

        return collect($response->json() ?? [])
            ->map(fn (array $item) => TraccarDevice::fromArray($item))
            ->values();
    }

    public function findDeviceByUniqueId(string $uniqueId): ?TraccarDevice
    {
        $response = $this->client()->get('/api/devices', [
            'uniqueId' => $uniqueId,
        ]);

        if (! $response->successful()) {
            $this->fail('Falha ao buscar dispositivo no Traccar.', $response->status(), $response->body());
        }

        $items = $response->json() ?? [];

        if ($items === []) {
            return null;
        }

        return TraccarDevice::fromArray($items[0]);
    }

    public function createDevice(string $uniqueId, string $name, ?string $model = null): TraccarDevice
    {
        $response = $this->client()->post('/api/devices', $this->devicePayload(
            uniqueId: $uniqueId,
            name: $name,
            model: $model,
        ));

        if (! $response->successful()) {
            $this->fail('Falha ao criar dispositivo no Traccar.', $response->status(), $response->body());
        }

        return TraccarDevice::fromArray($response->json() ?? []);
    }

    public function updateDevice(int $id, string $uniqueId, string $name, ?string $model = null): TraccarDevice
    {
        $response = $this->client()->put("/api/devices/{$id}", $this->devicePayload(
            uniqueId: $uniqueId,
            name: $name,
            model: $model,
            id: $id,
        ));

        if (! $response->successful()) {
            $this->fail('Falha ao atualizar dispositivo no Traccar.', $response->status(), $response->body());
        }

        return TraccarDevice::fromArray($response->json() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function devicePayload(string $uniqueId, string $name, ?string $model = null, ?int $id = null): array
    {
        return array_filter([
            'id' => $id,
            'name' => $name,
            'uniqueId' => $uniqueId,
            'model' => $model,
        ], fn (mixed $value) => $value !== null && $value !== '');
    }

    public function latestPositions(?array $deviceIds = null): Collection
    {
        $response = $this->client()->get('/api/positions');

        if (! $response->successful()) {
            $this->fail('Falha ao buscar posições no Traccar.', $response->status(), $response->body());
        }

        $positions = collect($response->json() ?? [])
            ->map(fn (array $item) => TraccarPosition::fromArray($item));

        if ($deviceIds !== null) {
            $allowed = array_flip($deviceIds);
            $positions = $positions->filter(
                fn (TraccarPosition $position) => isset($allowed[$position->deviceId]),
            );
        }

        return $positions->values();
    }

    public function positionHistory(int $deviceId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $response = $this->client()->get('/api/positions', [
            'deviceId' => $deviceId,
            'from' => $from->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'to' => $to->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ]);

        if (! $response->successful()) {
            $this->fail('Falha ao buscar histórico no Traccar.', $response->status(), $response->body());
        }

        return collect($response->json() ?? [])
            ->map(fn (array $item) => TraccarPosition::fromArray($item))
            ->sortBy(fn (TraccarPosition $position) => $position->recordedAt->timestamp)
            ->values();
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integração Traccar não configurada.');
        }

        $request = Http::baseUrl((string) config('traccar.base_url'))
            ->acceptJson()
            ->timeout((int) config('traccar.timeout', 15));

        $token = config('traccar.token');

        if (filled($token)) {
            return $request->withToken((string) $token);
        }

        return $request->withBasicAuth(
            (string) config('traccar.email'),
            (string) config('traccar.password'),
        );
    }

    private function fail(string $message, int $status, string $body): never
    {
        Log::warning('traccar.gateway_error', [
            'message' => $message,
            'status' => $status,
            'body' => mb_substr($body, 0, 500),
        ]);

        throw new RuntimeException($message);
    }
}
