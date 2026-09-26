<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Enums\AlertSeverity;
use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Models\AlertState;
use App\Modules\Alert\Support\TraccarAttributeReader;
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

    /**
     * Alertas habilitados por padrão em um veículo novo.
     *
     * @return list<AlertType>
     */
    public static function defaultEnabledTypes(): array
    {
        return [
            AlertType::SOS,
            AlertType::JAMMING,
            AlertType::DEVICE_ALARM,
            AlertType::OFFLINE,
        ];
    }

    /**
     * Alertas que enviam e-mail por padrão em um veículo novo.
     *
     * @return list<AlertType>
     */
    public static function defaultEmailTypes(): array
    {
        return [
            AlertType::SOS,
            AlertType::JAMMING,
            AlertType::DEVICE_ALARM,
        ];
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
                'notify_push' => true,
                'settings' => self::defaultSettings($type),
            ])->save();
        }
    }

    /**
     * Garante uma configuração por veículo para cada tipo configurável.
     * Os alarmes do dispositivo viram uma configuração por código (os críticos
     * começam habilitados); os demais tipos seguem o padrão do sistema.
     */
    public function ensureVehicleDefaults(Vehicle|int $vehicle, ?int $tenantId = null): void
    {
        $vehicleId = $vehicle instanceof Vehicle ? $vehicle->getKey() : $vehicle;

        if ($tenantId === null) {
            $tenantId = $vehicle instanceof Vehicle
                ? (int) $vehicle->tenant_id
                : (int) Vehicle::query()->withoutGlobalScopes()->whereKey($vehicleId)->value('tenant_id');
        }

        if ($tenantId === 0 || $vehicleId === 0) {
            return;
        }

        $existing = AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $vehicleId)
            ->get(['type', 'alarm_code'])
            ->map(fn (AlertConfig $config) => $this->configKey($config->type, $config->alarm_code))
            ->all();

        foreach (AlertType::configurable() as $type) {
            if ($type === AlertType::DEVICE_ALARM) {
                continue;
            }

            if (in_array($this->configKey($type, null), $existing, true)) {
                continue;
            }

            $this->createVehicleConfig($tenantId, $vehicleId, $type, null, [
                'is_enabled' => in_array($type, self::defaultEnabledTypes(), true),
                'notify_email' => in_array($type, self::defaultEmailTypes(), true),
            ]);
        }

        foreach (TraccarAttributeReader::configurableDeviceAlarms() as $alarm) {
            if (in_array($this->configKey(AlertType::DEVICE_ALARM, $alarm['code']), $existing, true)) {
                continue;
            }

            $critical = $alarm['severity'] === AlertSeverity::CRITICAL->value;

            $this->createVehicleConfig($tenantId, $vehicleId, AlertType::DEVICE_ALARM, $alarm['code'], [
                'name' => $alarm['label'],
                'is_enabled' => $critical,
                'notify_email' => $critical,
            ]);
        }
    }

    /**
     * @param  array{name?: string, is_enabled?: bool, notify_email?: bool}  $overrides
     */
    private function createVehicleConfig(
        int $tenantId,
        int $vehicleId,
        AlertType $type,
        ?string $alarmCode,
        array $overrides = [],
    ): AlertConfig {
        $config = new AlertConfig;
        $config->forceFill([
            'tenant_id' => $tenantId,
            'client_id' => null,
            'vehicle_id' => $vehicleId,
            'alarm_code' => $alarmCode,
            'name' => $overrides['name'] ?? $type->label(),
            'type' => $type,
            'is_enabled' => $overrides['is_enabled'] ?? true,
            'notify_in_app' => true,
            'notify_email' => $overrides['notify_email'] ?? false,
            'notify_push' => true,
            'settings' => self::defaultSettings($type),
        ])->save();

        return $config;
    }

    private function configKey(AlertType $type, ?string $alarmCode): string
    {
        return $type->value.'|'.($alarmCode ?? '');
    }

    /**
     * Configuração do alarme do dispositivo para o veículo. Se ainda não
     * existir, cataloga na hora uma linha desabilitada para o operador.
     */
    public function deviceAlarmConfig(Vehicle $vehicle, string $alarmCode): AlertConfig
    {
        $code = strtolower(trim($alarmCode));

        $config = AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $vehicle->tenant_id)
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', AlertType::DEVICE_ALARM->value)
            ->where('alarm_code', $code)
            ->orderBy('id')
            ->first();

        if ($config !== null) {
            return $config;
        }

        return $this->catalogDeviceAlarmConfig($vehicle, $code);
    }

    public function catalogDeviceAlarmConfig(Vehicle $vehicle, string $alarmCode): AlertConfig
    {
        $code = strtolower(trim($alarmCode));

        $catalog = null;

        foreach (TraccarAttributeReader::configurableDeviceAlarms() as $alarm) {
            if ($alarm['code'] === $code) {
                $catalog = $alarm;

                break;
            }
        }

        $severity = $catalog['severity'] ?? TraccarAttributeReader::deviceAlarmSeverity($code)->value;
        $critical = $severity === AlertSeverity::CRITICAL->value;

        return $this->createVehicleConfig(
            (int) $vehicle->tenant_id,
            (int) $vehicle->getKey(),
            AlertType::DEVICE_ALARM,
            $code,
            [
                'name' => $catalog['label'] ?? TraccarAttributeReader::deviceAlarmLabel($code),
                'is_enabled' => $critical,
                'notify_email' => $critical,
            ],
        );
    }

    /**
     * Aplica os valores informados pelo operador no cadastro do veículo,
     * criando as configurações padrão que ainda não existirem.
     *
     * @param  array<int, array<string, mixed>>  $configs
     */
    public function syncForVehicle(Vehicle $vehicle, array $configs): void
    {
        $this->ensureVehicleDefaults($vehicle);

        if ($configs === []) {
            return;
        }

        $existing = AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $vehicle->tenant_id)
            ->where('vehicle_id', $vehicle->getKey())
            ->get()
            ->keyBy(fn (AlertConfig $config) => $this->configKey($config->type, $config->alarm_code));

        foreach ($configs as $item) {
            if (! is_array($item) || empty($item['type'])) {
                continue;
            }

            $type = $item['type'] instanceof AlertType
                ? $item['type']
                : AlertType::tryFrom((string) $item['type']);

            if ($type === null || ! in_array($type, AlertType::configurable(), true)) {
                continue;
            }

            $alarmCode = isset($item['alarm_code']) && $item['alarm_code'] !== ''
                ? strtolower(trim((string) $item['alarm_code']))
                : null;

            $config = $existing->get($this->configKey($type, $alarmCode));

            if ($config === null && $type === AlertType::DEVICE_ALARM && $alarmCode !== null) {
                $config = $this->catalogDeviceAlarmConfig($vehicle, $alarmCode);
            }

            if ($config === null) {
                continue;
            }

            $config->forceFill([
                'is_enabled' => array_key_exists('is_enabled', $item)
                    ? (bool) $item['is_enabled']
                    : $config->is_enabled,
                'notify_in_app' => array_key_exists('notify_in_app', $item)
                    ? (bool) $item['notify_in_app']
                    : $config->notify_in_app,
                'notify_push' => array_key_exists('notify_push', $item)
                    ? (bool) $item['notify_push']
                    : $config->notify_push,
                'notify_email' => array_key_exists('notify_email', $item)
                    ? (bool) $item['notify_email']
                    : $config->notify_email,
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
     * Configurações do tipo definidas no cadastro do veículo (escopo por veículo).
     * A configuração por cliente não participa do motor: ela apenas silencia
     * as notificações do próprio cliente quando desligada.
     *
     * @return Collection<int, AlertConfig>
     */
    public function matchingForVehicle(Vehicle $vehicle, AlertType $type, bool $enabledOnly = true): Collection
    {
        $query = AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $vehicle->tenant_id)
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', $type->value);

        if ($enabledOnly) {
            $query->where('is_enabled', true);
        }

        return $query->orderBy('id')->get();
    }

    /**
     * O cliente desligou este alerta no portal? Nesse caso ele deixa de
     * receber notificações, mas o alarme continua valendo para o operador.
     */
    public function isClientSilenced(Vehicle $vehicle, AlertType $type): bool
    {
        if ($vehicle->client_id === null || ! in_array($type, AlertType::clientConfigurable(), true)) {
            return false;
        }

        return AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $vehicle->tenant_id)
            ->where('client_id', $vehicle->client_id)
            ->whereNull('vehicle_id')
            ->where('type', $type->value)
            ->where('is_enabled', false)
            ->exists();
    }

    /**
     * Garante uma configuração por cliente para cada tipo configurável.
     * O cliente começa recebendo e pode silenciar cada alerta no portal.
     */
    public function ensureClientDefaults(Client|int $client, ?int $tenantId = null): void
    {
        $clientId = $client instanceof Client ? $client->getKey() : $client;

        if ($tenantId === null) {
            $tenantId = $client instanceof Client
                ? (int) $client->tenant_id
                : (int) Client::query()->withoutGlobalScopes()->whereKey($clientId)->value('tenant_id');
        }

        if ($tenantId === 0 || $clientId === 0) {
            return;
        }

        foreach (AlertType::configurable() as $type) {
            $exists = AlertConfig::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('client_id', $clientId)
                ->whereNull('vehicle_id')
                ->where('type', $type->value)
                ->exists();

            if ($exists) {
                continue;
            }

            $config = new AlertConfig;
            $config->forceFill([
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
                'vehicle_id' => null,
                'name' => $type->label(),
                'type' => $type,
                'is_enabled' => true,
                'notify_in_app' => true,
                'notify_email' => true,
                'notify_push' => true,
                'settings' => self::defaultSettings($type),
            ])->save();
        }
    }

    /**
     * Configurações que o cliente vê/edita no portal (subconjunto simples),
     * na ordem definida em AlertType::clientConfigurable().
     *
     * @return Collection<int, AlertConfig>
     */
    public function clientConfigurations(Client|int $client): Collection
    {
        $clientId = $client instanceof Client ? $client->getKey() : $client;

        $this->ensureClientDefaults($client);

        $types = array_map(fn (AlertType $type) => $type->value, AlertType::clientConfigurable());

        return AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->whereNull('vehicle_id')
            ->whereIn('type', $types)
            ->get()
            ->sortBy(fn (AlertConfig $config) => array_search($config->type->value, $types, true))
            ->values();
    }

    public function setClientEnabled(Client|int $client, AlertType $type, bool $enabled): AlertConfig
    {
        $clientModel = $client instanceof Client
            ? $client
            : Client::query()->withoutGlobalScopes()->findOrFail($client);

        $this->ensureClientDefaults($clientModel);

        $config = AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $clientModel->tenant_id)
            ->where('client_id', $clientModel->getKey())
            ->whereNull('vehicle_id')
            ->where('type', $type->value)
            ->firstOrFail();

        // O portal do cliente só silencia/volta a receber: os canais são
        // definidos pelo operador no cadastro de cada veículo.
        $config->forceFill(['is_enabled' => $enabled])->save();

        return $config;
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

        $alarmCode = isset($data['alarm_code']) && $data['alarm_code'] !== ''
            ? strtolower(trim((string) $data['alarm_code']))
            : null;

        $config = new AlertConfig;
        $config->forceFill([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'vehicle_id' => $vehicleId,
            'alarm_code' => $alarmCode,
            'name' => $data['name'] ?? $type->label(),
            'type' => $type,
            'is_enabled' => $data['is_enabled'] ?? true,
            'notify_in_app' => $data['notify_in_app'] ?? true,
            'notify_email' => $data['notify_email'] ?? false,
            'notify_push' => $data['notify_push'] ?? true,
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
            'notify_push' => $data['notify_push'] ?? $config->notify_push,
            'settings' => $settings,
        ]);

        if (isset($data['type'])) {
            $config->type = $data['type'] instanceof AlertType
                ? $data['type']
                : AlertType::from((string) $data['type']);
        }

        if (array_key_exists('alarm_code', $data)) {
            $config->alarm_code = $data['alarm_code'] !== null && $data['alarm_code'] !== ''
                ? strtolower(trim((string) $data['alarm_code']))
                : null;
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
