<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertSoundPreference;
use App\Modules\Alert\Models\UserNotification;
use App\Modules\User\Models\User;

/**
 * Sons de notificação por usuário e tipo de alerta.
 *
 * O catálogo (chave, arquivo iOS e canal Android) vive em
 * config('notification.push.sounds') e é espelhado no app. O envio do push
 * resolve a chave escolhida pelo destinatário; sem preferência, usa o
 * som padrão do aparelho.
 */
class AlertSoundService
{
    public const DEFAULT_SOUND = 'default';

    public function __construct(private readonly AlertConfigService $configs) {}

    /**
     * @return array<string, array{file: string, channel: string}>
     */
    public function catalog(): array
    {
        $sounds = config('notification.push.sounds');

        if (! is_array($sounds) || $sounds === []) {
            return [
                self::DEFAULT_SOUND => ['file' => 'default', 'channel' => 'default'],
            ];
        }

        return $sounds;
    }

    /**
     * @return list<string>
     */
    public function availableSounds(): array
    {
        return array_keys($this->catalog());
    }

    public function isAvailable(string $sound): bool
    {
        return array_key_exists($sound, $this->catalog());
    }

    /**
     * Preferências do usuário no formato tipo => som.
     *
     * @return array<string, string>
     */
    public function preferencesFor(User|int $user): array
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return AlertSoundPreference::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->pluck('sound', 'type')
            ->all();
    }

    /**
     * Som que deve tocar na notificação deste usuário. Tipos que não são de
     * alerta (financeiro, manual, ...) seguem com o som padrão.
     */
    public function soundFor(UserNotification $notification): string
    {
        $type = AlertType::tryFrom((string) $notification->type);

        if ($type === null) {
            return self::DEFAULT_SOUND;
        }

        $sound = AlertSoundPreference::query()
            ->withoutGlobalScopes()
            ->where('user_id', $notification->user_id)
            ->where('type', $type->value)
            ->value('sound');

        return is_string($sound) && $this->isAvailable($sound)
            ? $sound
            : self::DEFAULT_SOUND;
    }

    public function set(User $user, AlertType $type, string $sound): AlertSoundPreference
    {
        $preference = AlertSoundPreference::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'user_id' => $user->getKey(),
                'type' => $type->value,
            ]);

        $preference->forceFill([
            'tenant_id' => $user->tenant_id,
            'sound' => $sound,
        ])->save();

        return $preference;
    }

    /**
     * Alertas disponíveis para escolher o som: o subconjunto que o cliente
     * controla (AlertType::clientConfigurable()), limitado aos que estão
     * habilitados para ele. Para usuários sem cliente (operadores), todos os
     * tipos aparecem.
     *
     * @return list<array{type: string, label: string, description: string, sound: string}>
     */
    public function alertsForUser(User $user): array
    {
        $enabled = $this->enabledTypesFor($user);
        $preferences = $this->preferencesFor($user);

        return collect(AlertType::clientConfigurable())
            ->filter(fn (AlertType $type) => $enabled[$type->value] ?? true)
            ->map(function (AlertType $type) use ($preferences) {
                $sound = $preferences[$type->value] ?? self::DEFAULT_SOUND;

                return [
                    'type' => $type->value,
                    'label' => $type->label(),
                    'description' => $type->description(),
                    'sound' => $this->isAvailable($sound) ? $sound : self::DEFAULT_SOUND,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * O cliente silenciou o alerta no portal? Nesse caso ele sai da lista de
     * escolha de som (a preferência salva é mantida para quando reativar).
     *
     * @return array<string, bool>
     */
    private function enabledTypesFor(User $user): array
    {
        if ($user->client_id === null) {
            return [];
        }

        return $this->configs->clientConfigurations($user->client_id)
            ->mapWithKeys(fn ($config) => [$config->type->value => (bool) $config->is_enabled])
            ->all();
    }
}
