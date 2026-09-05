<?php

namespace App\Modules\Tracking\Contracts;

use App\Modules\Tracking\DTOs\TraccarDevice;
use App\Modules\Tracking\DTOs\TraccarPosition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface TraccarGateway
{
    public function isConfigured(): bool;

    /**
     * @return Collection<int, TraccarDevice>
     */
    public function listDevices(): Collection;

    public function findDeviceByUniqueId(string $uniqueId): ?TraccarDevice;

    public function createDevice(string $uniqueId, string $name, ?string $model = null): TraccarDevice;

    public function updateDevice(int $id, string $uniqueId, string $name, ?string $model = null): TraccarDevice;

    /**
     * Últimas posições conhecidas. Sem deviceIds = todas do usuário Traccar.
     *
     * @param  list<int>|null  $deviceIds
     * @return Collection<int, TraccarPosition>
     */
    public function latestPositions(?array $deviceIds = null): Collection;

    /**
     * @return Collection<int, TraccarPosition>
     */
    public function positionHistory(int $deviceId, CarbonImmutable $from, CarbonImmutable $to): Collection;
}
