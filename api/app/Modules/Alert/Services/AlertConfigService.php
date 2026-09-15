<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Models\AlertState;
use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AlertConfigService
{
    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(AlertType $type): array
    {
        return match ($type) {
            AlertType::SPEED => [
                'speed_limit_kmh' => 80,
                'min_duration_seconds' => 30,
            ],
            AlertType::OFFLINE => [
                'offline_minutes' => 15,
            ],
            AlertType::BATTERY => [
                'battery_threshold' => 20,
            ],
            default => [],
        };
    }

    public function ensureDefaults(Tenant|int $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        foreach (AlertType::configurable() as $type) {
            $exists = AlertConfig::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('type', $type->value)
                ->whereNull('client_id')
                ->whereNull('vehicle_id')
                ->exists();

            if ($exists) {
                continue;
            }

            $config = new AlertConfig;
            $config->forceFill([
                'tenant_id' => $tenantId,
                'client_id' => null,
                'vehicle_id' => null,
                'name' => $type->label(),
                'type' => $type,
                'is_enabled' => true,
                'notify_in_app' => true,
                'notify_email' => $type === AlertType::SOS
                    || $type === AlertType::JAMMING
                    || $type === AlertType::DEVICE_ALARM,
                'settings' => self::defaultSettings($type),
            ])->save();
        }
    }

    /**
     * @return Collection<int, AlertConfig>
     */
    public function listForCurrentTenant(): Collection
    {
        return AlertConfig::query()
            ->with(['client', 'vehicle'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Configurações habilitadas do tipo que se aplicam ao veículo
     * (veículo específico, cliente do veículo, ou global).
     *
     * @return Collection<int, AlertConfig>
     */
    public function matchingForVehicle(Vehicle $vehicle, AlertType $type, bool $enabledOnly = true): Collection
    {
        $query = AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $vehicle->tenant_id)
            ->where('type', $type->value)
            ->where(function ($builder) use ($vehicle): void {
                $builder
                    ->where(function ($global): void {
                        $global->whereNull('vehicle_id')->whereNull('client_id');
                    })
                    ->orWhere('vehicle_id', $vehicle->getKey())
                    ->orWhere(function ($client) use ($vehicle): void {
                        $client->whereNull('vehicle_id')->where('client_id', $vehicle->client_id);
                    });
            });

        if ($enabledOnly) {
            $query->where('is_enabled', true);
        }

        return $query->orderByRaw('CASE WHEN vehicle_id IS NOT NULL THEN 0 WHEN client_id IS NOT NULL THEN 1 ELSE 2 END')->get();
    }

    /**
     * Compatível com testes legados: primeira config global do tipo (cria defaults se necessário).
     */
    public function findForTenant(int $tenantId, AlertType $type): AlertConfig
    {
        $this->ensureDefaults($tenantId);

        return AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type->value)
            ->whereNull('client_id')
            ->whereNull('vehicle_id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AlertConfig
    {
        $type = $data['type'] instanceof AlertType
            ? $data['type']
            : AlertType::from((string) $data['type']);

        [$clientId, $vehicleId] = $this->resolveScope(
            $data['client_id'] ?? null,
            $data['vehicle_id'] ?? null,
        );

        $tenantId = TenantContext::tenantId();

        if ($tenantId === null && $vehicleId !== null) {
            $tenantId = Vehicle::query()->withoutGlobalScopes()->find($vehicleId)?->tenant_id;
        }

        if ($tenantId === null && $clientId !== null) {
            $tenantId = Client::query()->withoutGlobalScopes()->find($clientId)?->tenant_id;
        }

        if ($tenantId === null) {
            throw ValidationException::withMessages([
                'type' => ['Tenant não resolvido para criar a configuração.'],
            ]);
        }

        $settings = array_merge(
            self::defaultSettings($type),
            is_array($data['settings'] ?? null) ? $data['settings'] : [],
        );

        $config = new AlertConfig;
        $config->forceFill([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'vehicle_id' => $vehicleId,
            'name' => $data['name'] ?? $type->label(),
            'type' => $type,
            'is_enabled' => $data['is_enabled'] ?? true,
            'notify_in_app' => $data['notify_in_app'] ?? true,
            'notify_email' => $data['notify_email'] ?? false,
            'settings' => $settings,
        ])->save();

        return $config->load(['client', 'vehicle']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AlertConfig $config, array $data): AlertConfig
    {
        $settings = $config->settingsWithDefaults();

        if (isset($data['settings']) && is_array($data['settings'])) {
            $settings = array_merge($settings, $data['settings']);
        }

        $clientId = array_key_exists('client_id', $data) || array_key_exists('vehicle_id', $data)
            ? ($data['client_id'] ?? null)
            : $config->client_id;
        $vehicleId = array_key_exists('client_id', $data) || array_key_exists('vehicle_id', $data)
            ? ($data['vehicle_id'] ?? null)
            : $config->vehicle_id;

        if (array_key_exists('client_id', $data) || array_key_exists('vehicle_id', $data)) {
            [$clientId, $vehicleId] = $this->resolveScope($clientId, $vehicleId);
        }

        $config->fill([
            'client_id' => $clientId,
            'vehicle_id' => $vehicleId,
            'name' => $data['name'] ?? $config->name,
            'is_enabled' => $data['is_enabled'] ?? $config->is_enabled,
            'notify_in_app' => $data['notify_in_app'] ?? $config->notify_in_app,
            'notify_email' => $data['notify_email'] ?? $config->notify_email,
            'settings' => $settings,
        ]);

        if (isset($data['type'])) {
            $config->type = $data['type'] instanceof AlertType
                ? $data['type']
                : AlertType::from((string) $data['type']);
        }

        $config->save();

        return $config->refresh()->load(['client', 'vehicle']);
    }

    public function delete(AlertConfig $config): void
    {
        AlertState::query()
            ->withoutGlobalScopes()
            ->where('alert_config_id', $config->getKey())
            ->delete();

        $config->delete();
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveScope(mixed $clientId, mixed $vehicleId): array
    {
        $clientId = $clientId !== null && $clientId !== '' ? (int) $clientId : null;
        $vehicleId = $vehicleId !== null && $vehicleId !== '' ? (int) $vehicleId : null;

        if ($clientId !== null && $vehicleId !== null) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Informe apenas veículo ou cliente, não ambos.'],
            ]);
        }

        $tenantId = TenantContext::tenantId();

        if ($vehicleId !== null) {
            $vehicle = Vehicle::query()->find($vehicleId);

            if ($vehicle === null || ($tenantId !== null && (int) $vehicle->tenant_id !== (int) $tenantId)) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['Veículo inválido.'],
                ]);
            }

            return [null, $vehicleId];
        }

        if ($clientId !== null) {
            $client = Client::query()->find($clientId);

            if ($client === null || ($tenantId !== null && (int) $client->tenant_id !== (int) $tenantId)) {
                throw ValidationException::withMessages([
                    'client_id' => ['Cliente inválido.'],
                ]);
            }

            return [$clientId, null];
        }

        return [null, null];
    }
}
