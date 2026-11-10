<?php

namespace App\Modules\ServiceOrder\Enums;

enum ServiceOrderStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FRUSTRATED = 'frustrated';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::IN_PROGRESS => 'Em andamento',
            self::COMPLETED => 'Concluída',
            self::FRUSTRATED => 'Frustrada',
            self::CANCELLED => 'Cancelada',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::IN_PROGRESS, self::FRUSTRATED, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::FRUSTRATED, self::CANCELLED],
            self::COMPLETED, self::FRUSTRATED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminal(), true);
    }

    /**
     * Situações finais: a OS não volta a ser editada nem executada.
     * "Frustrada" = o técnico foi ao local, mas não conseguiu fazer o serviço.
     *
     * @return list<self>
     */
    public static function terminal(): array
    {
        return [self::COMPLETED, self::FRUSTRATED, self::CANCELLED];
    }

    /**
     * Situações que exigem um motivo informado por quem as aplica.
     */
    public function requiresReason(): bool
    {
        return $this === self::CANCELLED || $this === self::FRUSTRATED;
    }
}
