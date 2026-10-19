<?php

namespace App\Modules\Scheduling\Support;

use Carbon\CarbonImmutable;

/**
 * Grade operacional de horários (padrão: 15 minutos).
 *
 * Todos os tempos calculados sobem para o próximo múltiplo da grade:
 * 14 → 15, 18 → 30, 31 → 45 etc.
 */
final class TimeGrid
{
    public static function size(): int
    {
        return max(1, (int) config('scheduling.slot_step_minutes', 15));
    }

    public static function minutes(int|float $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        $grid = self::size();

        return (int) (ceil($minutes / $grid) * $grid);
    }

    /**
     * Sobe o horário para a próxima marca da grade no fuso operacional.
     * Retorna em UTC, formato usado internamente pelo agendamento.
     */
    public static function ceil(CarbonImmutable $time): CarbonImmutable
    {
        $grid = self::size();
        $local = $time
            ->setTimezone((string) config('scheduling.timezone', 'America/Sao_Paulo'))
            ->startOfMinute();
        $remainder = ((int) $local->format('i')) % $grid;

        if ($remainder === 0 && $time->second === 0) {
            return $local->utc();
        }

        return $local
            ->addMinutes($remainder === 0 ? $grid : $grid - $remainder)
            ->utc();
    }
}
