<?php

namespace App\Modules\Tracking\Gateways;

use App\Modules\Tracking\Contracts\TraccarGateway;
use App\Modules\Tracking\DTOs\TraccarDevice;
use App\Modules\Tracking\DTOs\TraccarPosition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Gateway inerte usado quando Traccar está desabilitado.
 * Permite desenvolver/testar o domínio sem API externa.
 */
class NullTraccarGateway implements TraccarGateway
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function listDevices(): Collection
    {
        return collect();
    }

    public function findDeviceByUniqueId(string $uniqueId): ?TraccarDevice
    {
        return null;
    }

    public function createDevice(string $uniqueId, string $name, ?string $model = null): TraccarDevice
    {
        throw new \RuntimeException('Integração Traccar não configurada.');
    }

    public function updateDevice(int $id, string $uniqueId, string $name, ?string $model = null): TraccarDevice
    {
        throw new \RuntimeException('Integração Traccar não configurada.');
    }

    public function latestPositions(?array $deviceIds = null): Collection
    {
        return collect();
    }

    public function positionHistory(int $deviceId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return collect();
    }
}
