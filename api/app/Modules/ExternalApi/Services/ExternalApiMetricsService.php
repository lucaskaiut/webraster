<?php

namespace App\Modules\ExternalApi\Services;

use App\Modules\ExternalApi\Models\ExternalApiRequest;
use App\Modules\ExternalApi\Support\ExternalApiCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalApiMetricsService
{
    public const GRANULARITIES = ['minute', 'hour', 'day', 'week', 'month'];

    /** Evita gráficos ilegíveis (e consultas pesadas) com granularidade fina demais. */
    public const MAX_BUCKETS = 1500;

    public function __construct(private readonly ExternalApiCatalog $catalog) {}

    /**
     * Série temporal de chamadas por serviço, agrupada no fuso do painel.
     *
     * @param  list<string>  $services
     * @return array<string, mixed>
     */
    public function metrics(CarbonImmutable $from, CarbonImmutable $to, string $granularity, string $timezone, array $services = []): array
    {
        $buckets = $this->buckets($from, $to, $granularity, $timezone);
        $index = array_flip($buckets);

        // O banco agrupa em UTC por minuto ou hora; a dobra para dia, semana
        // e mês acontece aqui, já no fuso do painel.
        $sqlBucket = $this->bucketExpression($granularity === 'minute' ? 'minute' : 'hour');

        $rows = $this->scoped($from, $to, $services)
            ->toBase()
            ->selectRaw($sqlBucket.' as bucket, service, count(*) as total, sum(case when succeeded = 0 then 1 else 0 end) as errors')
            ->groupBy('bucket', 'service')
            ->get();

        $series = [];

        foreach ($rows as $row) {
            $key = $this->bucketKey(
                CarbonImmutable::parse((string) $row->bucket, 'UTC')->setTimezone($timezone),
                $granularity,
            );

            if (! isset($index[$key])) {
                continue;
            }

            $service = (string) $row->service;
            $series[$service] ??= [
                'service' => $service,
                'label' => $this->catalog->label($service),
                'data' => array_fill(0, count($buckets), 0),
                'errors' => array_fill(0, count($buckets), 0),
                'total' => 0,
                'error_total' => 0,
            ];

            $series[$service]['data'][$index[$key]] += (int) $row->total;
            $series[$service]['errors'][$index[$key]] += (int) $row->errors;
            $series[$service]['total'] += (int) $row->total;
            $series[$service]['error_total'] += (int) $row->errors;
        }

        $order = array_flip(array_column($this->catalog->services(), 'key'));
        uasort($series, fn (array $a, array $b) => ($order[$a['service']] ?? PHP_INT_MAX) <=> ($order[$b['service']] ?? PHP_INT_MAX));

        $summary = $this->scoped($from, $to, $services)
            ->toBase()
            ->selectRaw('count(*) as total, sum(case when succeeded = 0 then 1 else 0 end) as errors, avg(duration_ms) as avg_duration')
            ->first();

        $origins = $this->scoped($from, $to, $services)
            ->toBase()
            ->selectRaw('origin, service, count(*) as total')
            ->groupBy('origin', 'service')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn (object $row) => [
                'origin' => $row->origin,
                'service' => (string) $row->service,
                'label' => $this->catalog->label((string) $row->service),
                'total' => (int) $row->total,
            ])
            ->all();

        return [
            'granularity' => $granularity,
            'timezone' => $timezone,
            'from' => $from->setTimezone($timezone)->toIso8601String(),
            'to' => $to->setTimezone($timezone)->toIso8601String(),
            'buckets' => $buckets,
            'series' => array_values($series),
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'errors' => (int) ($summary->errors ?? 0),
                'avg_duration_ms' => $summary?->avg_duration !== null ? (int) round((float) $summary->avg_duration) : null,
            ],
            'top_origins' => $origins,
        ];
    }

    /**
     * @param  array{service?: string|null, status?: string|null, search?: string|null}  $filters
     */
    public function paginate(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $perPage): LengthAwarePaginator
    {
        $services = filled($filters['service'] ?? null) ? [(string) $filters['service']] : [];

        return $this->scoped($from, $to, $services)
            ->select([
                'id', 'provider', 'service', 'method', 'url', 'status_code', 'succeeded',
                'duration_ms', 'error', 'origin', 'tenant_id', 'user_id', 'created_at',
            ])
            ->with(['tenant:id,name', 'user:id,name'])
            ->when(($filters['status'] ?? null) === 'success', fn (Builder $query) => $query->where('succeeded', true))
            ->when(($filters['status'] ?? null) === 'error', fn (Builder $query) => $query->where('succeeded', false))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
                $query->where(fn (Builder $inner) => $inner->where('url', 'like', $term)->orWhere('origin', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Rótulos dos intervalos no fuso do painel, do início do primeiro ao último.
     *
     * @return list<string>
     */
    public function buckets(CarbonImmutable $from, CarbonImmutable $to, string $granularity, string $timezone): array
    {
        $cursor = $this->startOf($from->setTimezone($timezone), $granularity);
        $end = $to->setTimezone($timezone);
        $buckets = [];

        while ($cursor <= $end) {
            $buckets[] = $this->bucketKey($cursor, $granularity);

            if (count($buckets) > self::MAX_BUCKETS) {
                throw ValidationException::withMessages([
                    'granularity' => 'Período longo demais para essa granularidade. Escolha uma granularidade maior ou reduza o período.',
                ]);
            }

            $cursor = match ($granularity) {
                'minute' => $cursor->addMinute(),
                'hour' => $cursor->addHour(),
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                default => $cursor->addMonthNoOverflow(),
            };
        }

        return $buckets;
    }

    /**
     * @param  list<string>  $services
     * @return Builder<ExternalApiRequest>
     */
    private function scoped(CarbonImmutable $from, CarbonImmutable $to, array $services): Builder
    {
        return ExternalApiRequest::query()
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->when($services !== [], fn (Builder $query) => $query->whereIn('service', $services));
    }

    private function startOf(CarbonImmutable $date, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'minute' => $date->startOfMinute(),
            'hour' => $date->startOfHour(),
            'day' => $date->startOfDay(),
            'week' => $date->startOfWeek(CarbonImmutable::MONDAY),
            default => $date->startOfMonth(),
        };
    }

    private function bucketKey(CarbonImmutable $date, string $granularity): string
    {
        return $this->startOf($date, $granularity)->toIso8601String();
    }

    private function bucketExpression(string $unit): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => $unit === 'minute'
                ? "strftime('%Y-%m-%d %H:%M:00', created_at)"
                : "strftime('%Y-%m-%d %H:00:00', created_at)",
            'pgsql' => $unit === 'minute'
                ? "to_char(created_at, 'YYYY-MM-DD HH24:MI:00')"
                : "to_char(created_at, 'YYYY-MM-DD HH24:00:00')",
            default => $unit === 'minute'
                ? "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00')"
                : "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
        };
    }
}
