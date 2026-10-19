<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Scheduling\Support\Coordinates;
use App\Modules\Scheduling\Support\TimeGrid;
use App\Modules\Scheduling\Support\TravelEstimate;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserWorkHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Regras de agendamento: duração pelos serviços, deslocamento encadeado entre
 * atendimentos, jornada de trabalho, conflitos e sugestão de horários.
 *
 * A agenda do dia é tratada como uma sequência:
 * base → OS 1 → OS 2 → ... → OS n → base.
 */
class SchedulingService
{
    private const MAX_SLOTS = 96;

    public function __construct(
        private readonly Geocoder $geocoder,
        private readonly RouteEstimator $routes,
    ) {}

    public function timezone(): string
    {
        return (string) config('scheduling.timezone', 'America/Sao_Paulo');
    }

    /**
     * Endereço formatado do cliente e coordenadas (geocodificadas e gravadas quando o endereço muda).
     *
     * @return array{address: string, coordinates: ?Coordinates}
     */
    public function clientLocation(Client $client): array
    {
        $address = Geocoder::formatAddress($client->only(['street', 'number', 'neighborhood', 'city', 'state', 'zip']));

        if ($address === '') {
            return ['address' => '', 'coordinates' => null];
        }

        $stored = Coordinates::tryFrom($client->latitude, $client->longitude);

        if ($stored !== null && $client->geocoded_address === $address) {
            return ['address' => $address, 'coordinates' => $stored];
        }

        $coordinates = $this->geocoder->geocode($address);

        if ($coordinates !== null) {
            $client->forceFill([
                'latitude' => $coordinates->latitude,
                'longitude' => $coordinates->longitude,
                'geocoded_address' => $address,
            ])->saveQuietly();
        }

        return ['address' => $address, 'coordinates' => $coordinates];
    }

    public function geocode(?string $address): ?Coordinates
    {
        return $this->geocoder->geocode($address);
    }

    public function technician(?int $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        return User::query()
            ->with('workHours')
            ->whereKey($userId)
            ->where('type', UserType::TECHNICIAN->value)
            ->where('technician_active', true)
            ->first();
    }

    /**
     * Coordenadas da base do técnico, geocodificando o endereço-base quando ainda não houver.
     */
    public function technicianBase(User $technician): ?Coordinates
    {
        $coordinates = $technician->baseCoordinates();

        if ($coordinates !== null) {
            return $coordinates;
        }

        $coordinates = $this->geocoder->geocode($technician->baseAddress());

        if ($coordinates !== null) {
            $technician->forceFill([
                'latitude' => $coordinates->latitude,
                'longitude' => $coordinates->longitude,
            ])->saveQuietly();
        }

        return $coordinates;
    }

    /**
     * Rota entre dois pontos. Pontos iguais não precisam de chamada externa.
     */
    public function routeBetween(?Coordinates $from, ?Coordinates $to): ?TravelEstimate
    {
        if ($from === null || $to === null) {
            return null;
        }

        if (
            abs($from->latitude - $to->latitude) < 1e-7
            && abs($from->longitude - $to->longitude) < 1e-7
        ) {
            return new TravelEstimate(
                0,
                0,
                TravelEstimate::SOURCE_ESTIMATE,
                [[$from->latitude, $from->longitude]],
            );
        }

        return $this->routes->estimate($from, $to);
    }

    /**
     * Soma do tempo estimado dos serviços × quantidade.
     *
     * @param  list<array<string, mixed>>  $items  com service_id (id interno) e quantity
     */
    public function durationForItems(array $items): ?int
    {
        $ids = collect($items)->pluck('service_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $services = Service::query()->whereIn('id', $ids)->get()->keyBy('id');
        $total = 0;

        foreach ($items as $item) {
            $service = $services->get((int) ($item['service_id'] ?? 0));

            if ($service !== null) {
                $total += (int) $service->estimated_minutes * max(1, (int) ($item['quantity'] ?? 1));
            }
        }

        return $total > 0 ? TimeGrid::minutes($total) : null;
    }

    /**
     * Problemas de disponibilidade para o atendimento proposto, considerando
     * os deslocamentos desde o atendimento anterior e até o próximo.
     *
     * @return list<string>
     */
    public function availabilityIssues(
        int $technicianId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?Coordinates $destination = null,
        ?int $ignoreOrderId = null,
    ): array {
        $technician = $this->technician($technicianId);
        $base = $technician !== null ? $this->technicianBase($technician) : null;
        $orders = $this->dayOrders($technicianId, $start, $ignoreOrderId);
        $context = $this->contextForInterval($orders, $base, $destination, $start, $end);

        if ($context['overlap'] !== null) {
            return [sprintf(
                'Conflito de agenda com %s (%s).',
                $context['overlap']->code,
                $context['overlap']->client?->name ?? 'OS',
            )];
        }

        $hasWorkHours = $technician?->workHours->isNotEmpty() ?? false;
        $interval = $hasWorkHours ? $this->workIntervalFor($technician, $start, $end) : null;

        if ($hasWorkHours && $interval === null) {
            return ['Horário fora da jornada de trabalho do técnico.'];
        }

        $fromMinutes = $context['from_travel']?->minutes ?? 0;
        $toMinutes = $context['to_travel']?->minutes ?? 0;

        // O deslocamento da base não consome o início/fim da jornada: o
        // primeiro atendimento pode começar no início e o último terminar no fim.
        $earliest = $context['previous'] !== null
            ? $this->orderInterval($context['previous'])[1]->addMinutes($fromMinutes)
            : $interval[0] ?? null;

        $latestEnd = $context['next'] !== null
            ? $this->orderInterval($context['next'])[0]->subMinutes($toMinutes)
            : $interval[1] ?? null;

        $issues = [];

        if ($earliest !== null && $start->lessThan($earliest)) {
            $issues[] = $context['previous'] !== null
                ? sprintf(
                    'Deslocamento insuficiente desde %s (%s).',
                    $context['previous']->code,
                    $context['previous']->client?->name ?? 'OS',
                )
                : 'Sem tempo para sair da base até o atendimento.';
        }

        if ($latestEnd !== null && $end->greaterThan($latestEnd)) {
            $issues[] = $context['next'] !== null
                ? sprintf(
                    'Sem tempo para chegar à próxima OS %s (%s).',
                    $context['next']->code,
                    $context['next']->client?->name ?? 'OS',
                )
                : 'Sem tempo para retornar à base após o atendimento.';
        }

        return $issues;
    }

    /**
     * Horários do dia (fuso operacional). Horários ocupados ou inviáveis também
     * são retornados, marcados como indisponíveis e com o motivo.
     *
     * @return list<array{
     *     start: string,
     *     end: string,
     *     available: bool,
     *     reason: ?string,
     *     blocked_by: ?array{
     *         id: string,
     *         code: string,
     *         client: ?string,
     *         service_start: ?string,
     *         service_end: ?string,
     *         blocked_start: string,
     *         blocked_end: string
     *     }
     * }>
     */
    public function suggestSlots(
        int $technicianId,
        string $date,
        int $durationMinutes,
        ?Coordinates $destination = null,
        ?int $ignoreOrderId = null,
    ): array {
        $technician = $this->technician($technicianId);
        $intervals = $this->workIntervals($technician, $date);

        if ($intervals === []) {
            return [];
        }

        $durationMinutes = TimeGrid::minutes($durationMinutes);
        $reference = CarbonImmutable::parse($date, $this->timezone())->startOfDay();
        $base = $technician !== null ? $this->technicianBase($technician) : null;
        $orders = $this->dayOrders($technicianId, $reference, $ignoreOrderId);
        $step = TimeGrid::size();
        $now = CarbonImmutable::now();
        $slots = [];

        foreach ($intervals as [$workStart, $workEnd]) {
            $candidate = TimeGrid::ceil($workStart);

            while ($candidate->addMinutes($durationMinutes)->lessThanOrEqualTo($workEnd)) {
                $end = $candidate->addMinutes($durationMinutes);

                if ($candidate->lessThan($now)) {
                    $candidate = $candidate->addMinutes($step);

                    continue;
                }

                $context = $this->contextForInterval($orders, $base, $destination, $candidate, $end);
                $available = true;
                $reason = null;
                $blockedOrder = null;

                if ($context['overlap'] !== null) {
                    $available = false;
                    $blockedOrder = $context['overlap'];
                    $reason = sprintf(
                        'Conflito com %s (%s)',
                        $blockedOrder->code,
                        $blockedOrder->client?->name ?? 'OS',
                    );
                } else {
                    $fromMinutes = $context['from_travel']?->minutes ?? 0;
                    $toMinutes = $context['to_travel']?->minutes ?? 0;

                    // Sem OS anterior/próxima, o deslocamento da base não
                    // restringe o início/fim da jornada.
                    $earliest = $context['previous'] !== null
                        ? $this->orderInterval($context['previous'])[1]->addMinutes($fromMinutes)
                        : $workStart;

                    $latestEnd = $context['next'] !== null
                        ? $this->orderInterval($context['next'])[0]->subMinutes($toMinutes)
                        : $workEnd;

                    if ($candidate->lessThan($earliest)) {
                        $available = false;
                        $blockedOrder = $context['previous'];

                        $reason = $context['previous'] !== null
                            ? sprintf(
                                'Deslocamento insuficiente desde %s (%s)',
                                $context['previous']->code,
                                $context['previous']->client?->name ?? 'OS',
                            )
                            : 'Sem tempo para sair da base';
                    } elseif ($end->greaterThan($latestEnd)) {
                        $available = false;
                        $blockedOrder = $context['next'];

                        $reason = $context['next'] !== null
                            ? sprintf(
                                'Sem tempo para chegar à próxima OS %s (%s)',
                                $context['next']->code,
                                $context['next']->client?->name ?? 'OS',
                            )
                            : 'Sem tempo para retornar à base';
                    }
                }

                $slots[] = [
                    'start' => $candidate->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'available' => $available,
                    'reason' => $reason,
                    'blocked_by' => $blockedOrder !== null
                        ? $this->blockedByPayload($blockedOrder)
                        : null,
                ];

                if (count($slots) >= self::MAX_SLOTS) {
                    return $slots;
                }

                $candidate = $candidate->addMinutes($step);
            }
        }

        return $slots;
    }

    /**
     * Intervalos de trabalho do dia em UTC. Sem jornada cadastrada, usa 08:00–18:00.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function workIntervals(?User $technician, string $date): array
    {
        $day = CarbonImmutable::parse($date, $this->timezone())->startOfDay();

        $hours = $technician?->workHours->isNotEmpty()
            ? $technician->workHours->where('weekday', $day->dayOfWeek)
                ->map(fn (UserWorkHour $hour) => [
                    UserWorkHour::normalizeTime((string) $hour->starts_at),
                    UserWorkHour::normalizeTime((string) $hour->ends_at),
                ])
                ->values()
                ->all()
            : [['08:00', '18:00']];

        $intervals = [];

        foreach ($hours as [$from, $to]) {
            $start = CarbonImmutable::parse($day->format('Y-m-d').' '.$from, $this->timezone())->utc();
            $end = CarbonImmutable::parse($day->format('Y-m-d').' '.$to, $this->timezone())->utc();

            if ($end->greaterThan($start)) {
                $intervals[] = [$start, $end];
            }
        }

        usort($intervals, fn (array $a, array $b) => $a[0] <=> $b[0]);

        return $intervals;
    }

    /**
     * OS que ocupam a agenda do técnico no dia, em ordem cronológica.
     *
     * @return Collection<int, ServiceOrder>
     */
    public function dayOrders(int $technicianId, CarbonImmutable $reference, ?int $ignoreOrderId = null): Collection
    {
        $local = $reference->setTimezone($this->timezone());
        $dayStart = $local->startOfDay()->utc();
        $dayEnd = $local->endOfDay()->utc();

        return ServiceOrder::query()
            ->with('client')
            ->where('technician_id', $technicianId)
            ->whereNotIn('status', [
                ServiceOrderStatus::CANCELLED->value,
                ServiceOrderStatus::COMPLETED->value,
            ])
            ->when($ignoreOrderId, fn ($query) => $query->where('id', '!=', $ignoreOrderId))
            ->whereNotNull('scheduled_start_at')
            ->whereBetween('scheduled_start_at', [$dayStart, $dayEnd])
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->get()
            ->values();
    }

    /**
     * Recalcula e persiste os trechos de deslocamento de todas as OS do dia:
     * cada OS recebe a origem da OS anterior (ou base) e o destino da próxima
     * OS (ou base).
     */
    public function syncDayLegs(int $technicianId, CarbonImmutable $reference): void
    {
        $technician = User::query()->find($technicianId);
        $base = $technician !== null ? $this->technicianBase($technician) : null;
        $orders = $this->dayOrders($technicianId, $reference);
        $total = $orders->count();

        foreach ($orders as $index => $order) {
            $previous = $index > 0 ? $orders->get($index - 1) : null;
            $next = $index + 1 < $total ? $orders->get($index + 1) : null;

            $orderCoordinates = $this->orderCoordinates($order);
            $previousCoordinates = $previous !== null ? $this->orderCoordinates($previous) : null;
            $nextCoordinates = $next !== null ? $this->orderCoordinates($next) : null;

            $fromOrigin = $previousCoordinates ?? $base;
            $toDestination = $nextCoordinates ?? $base;

            $fromTravel = $this->routeBetween($fromOrigin, $orderCoordinates);
            $toTravel = $this->routeBetween($orderCoordinates, $toDestination);

            $order->timestamps = false;
            $order->forceFill([
                'travel_from_order_id' => $previousCoordinates !== null ? $previous?->getKey() : null,
                'estimated_travel_minutes' => $fromTravel?->minutes,
                'travel_distance_meters' => $fromTravel?->distanceMeters,
                'travel_to_order_id' => $nextCoordinates !== null ? $next?->getKey() : null,
                'estimated_return_travel_minutes' => $toTravel?->minutes,
                'return_travel_distance_meters' => $toTravel?->distanceMeters,
            ])->saveQuietly();
            $order->timestamps = true;
        }
    }

    /**
     * Contexto de deslocamento de um horário candidato (usado na prévia do painel).
     *
     * @return array<string, mixed>
     */
    public function previewTravel(
        int $technicianId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?Coordinates $destination,
        ?int $ignoreOrderId = null,
    ): array {
        $technician = User::query()->find($technicianId);
        $base = $technician !== null ? $this->technicianBase($technician) : null;
        $orders = $this->dayOrders($technicianId, $start, $ignoreOrderId);

        return $this->contextForInterval($orders, $base, $destination, $start, $end) + [
            'base_address' => $technician?->baseAddress() ?: null,
        ];
    }

    /**
     * Intervalo ocupado pela OS, incluindo os deslocamentos persistidos de
     * entrada e saída. Usado para exibição dos bloqueios.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function occupiedInterval(ServiceOrder $order): array
    {
        $start = CarbonImmutable::parse($order->scheduled_start_at);
        $end = $order->scheduled_end_at
            ? CarbonImmutable::parse($order->scheduled_end_at)
            : $start->addMinutes($order->estimated_duration_minutes ?: (int) config('scheduling.default_duration_minutes', 60));

        $fromMinutes = TimeGrid::minutes((int) $order->estimated_travel_minutes);
        $toMinutes = TimeGrid::minutes(
            $order->estimated_return_travel_minutes !== null
                ? (int) $order->estimated_return_travel_minutes
                : $fromMinutes,
        );

        return [$start->subMinutes($fromMinutes), $end->addMinutes($toMinutes)];
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @return array{
     *     previous: ?ServiceOrder,
     *     next: ?ServiceOrder,
     *     overlap: ?ServiceOrder,
     *     from_origin: ?Coordinates,
     *     to_destination: ?Coordinates,
     *     from_order: ?ServiceOrder,
     *     to_order: ?ServiceOrder,
     *     from_travel: ?TravelEstimate,
     *     to_travel: ?TravelEstimate
     * }
     */
    private function contextForInterval(
        Collection $orders,
        ?Coordinates $base,
        ?Coordinates $destination,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $previous = null;
        $next = null;
        $overlap = null;

        foreach ($orders as $order) {
            [$orderStart, $orderEnd] = $this->orderInterval($order);

            if ($this->overlaps($start, $end, $orderStart, $orderEnd)) {
                $overlap = $order;
                break;
            }

            if ($orderEnd->lessThanOrEqualTo($start)) {
                $previous = $order;

                continue;
            }

            if ($orderStart->greaterThanOrEqualTo($end)) {
                $next = $order;
                break;
            }
        }

        $previousCoordinates = $previous !== null ? $this->orderCoordinates($previous) : null;
        $nextCoordinates = $next !== null ? $this->orderCoordinates($next) : null;
        $fromOrigin = $previousCoordinates ?? $base;
        $toDestination = $nextCoordinates ?? $base;

        return [
            'previous' => $previous,
            'next' => $next,
            'overlap' => $overlap,
            'from_origin' => $fromOrigin,
            'to_destination' => $toDestination,
            'from_order' => $previousCoordinates !== null ? $previous : null,
            'to_order' => $nextCoordinates !== null ? $next : null,
            'from_travel' => $this->routeBetween($fromOrigin, $destination),
            'to_travel' => $this->routeBetween($destination, $toDestination),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function orderInterval(ServiceOrder $order): array
    {
        $start = CarbonImmutable::parse($order->scheduled_start_at);
        $end = $order->scheduled_end_at
            ? CarbonImmutable::parse($order->scheduled_end_at)
            : $start->addMinutes($order->estimated_duration_minutes ?: (int) config('scheduling.default_duration_minutes', 60));

        return [$start, $end];
    }

    private function orderCoordinates(ServiceOrder $order): ?Coordinates
    {
        return Coordinates::tryFrom($order->latitude, $order->longitude);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function workIntervalFor(?User $technician, CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        $localDate = $start->setTimezone($this->timezone())->format('Y-m-d');

        foreach ($this->workIntervals($technician, $localDate) as $interval) {
            if ($start->greaterThanOrEqualTo($interval[0]) && $end->lessThanOrEqualTo($interval[1])) {
                return $interval;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     client: ?string,
     *     service_start: ?string,
     *     service_end: ?string,
     *     blocked_start: string,
     *     blocked_end: string
     * }
     */
    private function blockedByPayload(ServiceOrder $order): array
    {
        $interval = $this->occupiedInterval($order);

        return [
            'id' => $order->uuid,
            'code' => $order->code,
            'client' => $order->client?->name,
            'service_start' => $order->scheduled_start_at?->toIso8601String(),
            'service_end' => $order->scheduled_end_at?->toIso8601String(),
            'blocked_start' => $interval[0]->toIso8601String(),
            'blocked_end' => $interval[1]->toIso8601String(),
        ];
    }

    private function overlaps(
        CarbonImmutable $startA,
        CarbonImmutable $endA,
        CarbonImmutable $startB,
        CarbonImmutable $endB,
    ): bool {
        return $startA->lessThan($endB) && $endA->greaterThan($startB);
    }
}
