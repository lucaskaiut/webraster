<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertSoundPreference;
use App\Modules\Alert\Models\UserNotification;
use App\Modules\User\Models\User;

/**
 * Sons de notificação por usuário e grupo de alerta.
 *
 * O app exibe apenas três grupos (ignição, cerca e alimentação cortada);
 * cada grupo cobre um conjunto de notificações e mantém um único som. O
 * catálogo (chave, arquivo iOS e canal Android) vive em
 * config('notification.push.sounds') e é espelhado no app. O envio do push
 * resolve a chave escolhida pelo destinatário; sem preferência, usa o som
 * padrão do aparelho (ou a preferência antiga por tipo, quando existir).
 */
class AlertSoundService
{
    public const DEFAULT_SOUND = 'default';

    public const GROUP_IGNITION = 'ignition';

    public const GROUP_GEOFENCE = 'geofence';

    public const GROUP_POWERCUT = 'powercut';

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
     * Grupos de som configuráveis no app.
     *
     * @return list<array{type: string, label: string, description: string}>
     */
    public static function groups(): array
    {
        return [
            [
                'type' => self::GROUP_IGNITION,
                'label' => 'Ignição ligada / desligada',
                'description' => 'Som dos avisos de ignição ligada e desligada.',
            ],
            [
                'type' => self::GROUP_GEOFENCE,
                'label' => 'Entrada e saída de cerca',
                'description' => 'Som dos avisos de entrada e saída de geocercas.',
            ],
            [
                'type' => self::GROUP_POWERCUT,
                'label' => 'Alimentação cortada',
                'description' => 'Som do alarme de alimentação cortada do rastreador.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function groupKeys(): array
    {
        return array_map(fn (array $group) => $group['type'], self::groups());
    }

    /**
     * Preferências do usuário no formato chave => som. A chave pode ser um
     * grupo do app (ignition, geofence, powercut) ou um tipo antigo.
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
     * Som que deve tocar na notificação deste usuário. O grupo tem prioridade;
     * tipos sem grupo (sos, offline, bateria, ...) mantêm a preferência por tipo.
     */
    public function soundFor(UserNotification $notification): string
    {
        $preferences = $this->preferencesFor($notification->user_id);
        $group = $this->groupFor($notification);

        if ($group !== null) {
            $sound = $preferences[$group] ?? $preferences[$notification->type] ?? null;

            if (is_string($sound) && $this->isAvailable($sound)) {
                return $sound;
            }
        }

        $type = AlertType::tryFrom((string) $notification->type);

        if ($type !== null) {
            $sound = $preferences[$type->value] ?? null;

            if (is_string($sound) && $this->isAvailable($sound)) {
                return $sound;
            }
        }

        return self::DEFAULT_SOUND;
    }

    public function set(User $user, string $type, string $sound): AlertSoundPreference
    {
        $preference = AlertSoundPreference::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'user_id' => $user->getKey(),
                'type' => $type,
            ]);

        $preference->forceFill([
            'tenant_id' => $user->tenant_id,
            'sound' => $sound,
        ])->save();

        return $preference;
    }

    /**
     * Grupos exibidos no app, com o som escolhido por grupo.
     *
     * @return list<array{type: string, label: string, description: string, sound: string}>
     */
    public function alertsForUser(User $user): array
    {
        $preferences = $this->preferencesFor($user);

        return array_map(function (array $group) use ($preferences): array {
            $sound = $preferences[$group['type']] ?? self::DEFAULT_SOUND;

            return [
                ...$group,
                'sound' => $this->isAvailable($sound) ? $sound : self::DEFAULT_SOUND,
            ];
        }, self::groups());
    }

    /**
     * Grupo de som da notificação, quando ela pertence a um dos grupos do app.
     * O código `powercut` (rastreador) vem no meta do alerta de dispositivo.
     */
    private function groupFor(UserNotification $notification): ?string
    {
        $type = AlertType::tryFrom((string) $notification->type);

        return match ($type) {
            AlertType::IGNITION_ON, AlertType::IGNITION_OFF => self::GROUP_IGNITION,
            AlertType::GEOFENCE => self::GROUP_GEOFENCE,
            AlertType::DEVICE_ALARM => $this->isPowerCut($notification) ? self::GROUP_POWERCUT : null,
            default => null,
        };
    }

    private function isPowerCut(UserNotification $notification): bool
    {
        $meta = $notification->alert?->meta;
        $code = is_array($meta) ? ($meta['alarm_code'] ?? null) : null;

        return is_string($code) && strtolower(trim($code)) === 'powercut';
    }
}
