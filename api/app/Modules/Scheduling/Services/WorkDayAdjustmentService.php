<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserWorkDayOverride;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Antecipar/estender a jornada do técnico em um dia específico.
 *
 * A antecipação move o início do primeiro intervalo do dia e a extensão move
 * o fim do último; a jornada semanal não muda. Como o agendamento usa
 * SchedulingService::workIntervals(), os horários extras ficam disponíveis
 * para marcação automaticamente.
 */
class WorkDayAdjustmentService
{
    public const EARLIEST = '05:00';

    public const LATEST = '23:55';

    public function __construct(private readonly SchedulingService $scheduling) {}

    /**
     * @return array{
     *     date: string,
     *     has_work: bool,
     *     regular: ?array{starts_at: string, ends_at: string},
     *     effective: ?array{starts_at: string, ends_at: string},
     *     anticipated_to: ?string,
     *     extended_to: ?string,
     *     intervals: list<array{starts_at: string, ends_at: string}>,
     *     editable: bool
     * }
     */
    public function show(User $technician, string $date): array
    {
        $regular = $this->scheduling->regularWorkHours($technician, $date);
        $effective = $this->scheduling->applyWorkDayOverride(
            $regular,
            $this->scheduling->workDayOverride($technician, $date),
        );

        $hasWork = $regular !== [];

        return [
            'date' => $date,
            'has_work' => $hasWork,
            'regular' => $hasWork ? ['starts_at' => $regular[0][0], 'ends_at' => end($regular)[1]] : null,
            'effective' => $hasWork ? ['starts_at' => $effective[0][0], 'ends_at' => end($effective)[1]] : null,
            'anticipated_to' => $hasWork && $effective[0][0] !== $regular[0][0] ? $effective[0][0] : null,
            'extended_to' => $hasWork && end($effective)[1] !== end($regular)[1] ? end($effective)[1] : null,
            'intervals' => array_map(
                fn (array $hour) => ['starts_at' => $hour[0], 'ends_at' => $hour[1]],
                $effective,
            ),
            'editable' => $hasWork && ! $this->isPast($date),
        ];
    }

    /**
     * Grava o ajuste do dia. `null` em um dos lados remove aquele ajuste.
     */
    public function update(User $technician, string $date, ?string $startsAt, ?string $endsAt): array
    {
        if ($this->isPast($date)) {
            throw ValidationException::withMessages([
                'date' => 'Só é possível ajustar a jornada de hoje ou de dias futuros.',
            ]);
        }

        $regular = $this->scheduling->regularWorkHours($technician, $date);

        if ($regular === []) {
            throw ValidationException::withMessages([
                'date' => 'Não há jornada cadastrada neste dia.',
            ]);
        }

        if ($startsAt !== null && $startsAt >= $regular[0][0]) {
            throw ValidationException::withMessages([
                'starts_at' => sprintf('Para antecipar, escolha um horário antes de %s.', $regular[0][0]),
            ]);
        }

        if ($endsAt !== null && $endsAt <= end($regular)[1]) {
            throw ValidationException::withMessages([
                'ends_at' => sprintf('Para estender, escolha um horário depois de %s.', end($regular)[1]),
            ]);
        }

        $current = $this->scheduling->workDayOverride($technician, $date);
        $next = new UserWorkDayOverride(['starts_at' => $startsAt, 'ends_at' => $endsAt]);

        $this->ensureNoOrderIsLeftOut($technician, $date, $current, $next);

        if ($startsAt === null && $endsAt === null) {
            $current?->delete();
        } else {
            $technician->workDayOverrides()->updateOrCreate(
                ['date' => $date],
                ['starts_at' => $startsAt, 'ends_at' => $endsAt],
            );
        }

        return $this->show($technician, $date);
    }

    /**
     * Bloqueia a alteração quando uma OS em aberto marcada dentro da jornada
     * atual ficaria fora da nova jornada (ex.: desfazer a antecipação depois
     * que o agendamento já usou o horário extra).
     */
    private function ensureNoOrderIsLeftOut(
        User $technician,
        string $date,
        ?UserWorkDayOverride $current,
        UserWorkDayOverride $next,
    ): void {
        $regular = $this->scheduling->regularWorkHours($technician, $date);
        $before = $this->toUtc($date, $this->scheduling->applyWorkDayOverride($regular, $current));
        $after = $this->toUtc($date, $this->scheduling->applyWorkDayOverride($regular, $next));

        $reference = CarbonImmutable::parse($date, $this->scheduling->timezone())->startOfDay();
        $timezone = $this->scheduling->timezone();

        $blocked = $this->scheduling->dayOrders($technician->getKey(), $reference)
            ->filter(function (ServiceOrder $order) use ($before, $after) {
                [$start, $end] = $this->scheduling->serviceInterval($order);

                return $this->fits($start, $end, $before) && ! $this->fits($start, $end, $after);
            })
            ->map(function (ServiceOrder $order) use ($timezone) {
                [$start, $end] = $this->scheduling->serviceInterval($order);

                return sprintf(
                    'OS %s (%s–%s)',
                    $order->code,
                    $start->setTimezone($timezone)->format('H:i'),
                    $end->setTimezone($timezone)->format('H:i'),
                );
            })
            ->values();

        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages([
                'date' => sprintf(
                    'Não é possível alterar a jornada: %s %s agendada%s no horário que seria removido. Reagende antes de alterar.',
                    $blocked->implode(', '),
                    $blocked->count() > 1 ? 'estão' : 'está',
                    $blocked->count() > 1 ? 's' : '',
                ),
            ]);
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $hours
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function toUtc(string $date, array $hours): array
    {
        $timezone = $this->scheduling->timezone();

        return array_map(fn (array $hour) => [
            CarbonImmutable::parse($date.' '.$hour[0], $timezone)->utc(),
            CarbonImmutable::parse($date.' '.$hour[1], $timezone)->utc(),
        ], $hours);
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     */
    private function fits(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as [$from, $to]) {
            if ($start->greaterThanOrEqualTo($from) && $end->lessThanOrEqualTo($to)) {
                return true;
            }
        }

        return false;
    }

    private function isPast(string $date): bool
    {
        $today = CarbonImmutable::now($this->scheduling->timezone())->format('Y-m-d');

        return $date < $today;
    }
}
