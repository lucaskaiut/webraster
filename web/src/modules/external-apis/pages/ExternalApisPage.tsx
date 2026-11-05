import { useEffect, useMemo, useState, type CSSProperties } from 'react'
import { useSearchParams } from 'react-router'
import { Activity, RefreshCw } from 'lucide-react'
import {
  Button,
  Card,
  CardContent,
  CardHeader,
  DataTable,
  DateRangeFilter,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  SegmentedControl,
  Select,
  Skeleton,
  type Column,
} from '@/shared/design-system'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import { ExternalApiRequestModal } from '../components/ExternalApiRequestModal'
import { RequestsLineChart } from '../components/RequestsLineChart'
import { seriesColor } from '../utils/colors'
import { StatusBadge } from '../components/StatusBadge'
import {
  useExternalApiMetricsQuery,
  useExternalApiRequestsQuery,
  useExternalApiServicesQuery,
} from '../hooks/useExternalApis'
import type { ExternalApiRequest, Granularity } from '../types'
import {
  GRANULARITY_OPTIONS,
  NUMBER,
  PERIOD_OPTIONS,
  allowedGranularities,
  browserTimezone,
  defaultGranularity,
  resolvePeriod,
  type PeriodPreset,
} from '../utils/period'

const PER_PAGE = 20
const AUTO_REFRESH_MS = 60_000

function minuteAnchor(): Date {
  const now = new Date()
  now.setSeconds(0, 0)
  return now
}

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <Card className="p-5">
      <p className="text-sm text-muted">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums text-foreground">{value}</p>
      {hint && <p className="mt-0.5 text-xs text-subtle">{hint}</p>}
    </Card>
  )
}

export default function ExternalApisPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const preset = (searchParams.get('period') ?? '24h') as PeriodPreset
  const customFrom = searchParams.get('from') ?? ''
  const customTo = searchParams.get('to') ?? ''
  const service = searchParams.get('service') ?? ''
  const status = (searchParams.get('status') ?? '') as '' | 'success' | 'error'
  const page = Number(searchParams.get('page') ?? 1)
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [anchor, setAnchor] = useState(minuteAnchor)
  const timezone = useMemo(browserTimezone, [])

  const period = resolvePeriod(preset, anchor, { from: customFrom, to: customTo })
  const allowed = allowedGranularities(period.durationMs)
  const requested = searchParams.get('granularity') as Granularity | null
  const granularity: Granularity =
    requested && allowed.includes(requested) ? requested : defaultGranularity(period.durationMs)

  const granularityOptions = GRANULARITY_OPTIONS.filter((option) => allowed.includes(option.value))

  const changePeriod = (value: PeriodPreset) => update({ period: value === '24h' ? null : value, granularity: null })

  const update = (changes: Record<string, string | null>) =>
    setSearchParams(
      (params) => {
        for (const [key, value] of Object.entries(changes)) {
          if (value) params.set(key, value)
          else params.delete(key)
        }
        if (!('page' in changes)) params.delete('page')
        return params
      },
      { replace: true },
    )

  useEffect(() => {
    if ((searchParams.get('search') ?? '') !== debouncedSearch) update({ search: debouncedSearch || null })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedSearch])

  // Períodos relativos ("últimas 24h") avançam sozinhos.
  useEffect(() => {
    if (preset === 'custom') return
    const interval = window.setInterval(() => setAnchor(minuteAnchor()), AUTO_REFRESH_MS)
    return () => window.clearInterval(interval)
  }, [preset])

  const servicesQuery = useExternalApiServicesQuery()
  const metricsQuery = useExternalApiMetricsQuery({
    from: period.from,
    to: period.to,
    timezone,
    granularity,
    services: service ? [service] : undefined,
  })
  const requestsQuery = useExternalApiRequestsQuery({
    from: period.from,
    to: period.to,
    timezone,
    page,
    per_page: PER_PAGE,
    service: service || undefined,
    status: status || undefined,
    search: debouncedSearch || undefined,
  })

  const colorSlots = useMemo(
    () => Object.fromEntries((servicesQuery.data ?? []).map((item, index) => [item.key, index])),
    [servicesQuery.data],
  )

  const metrics = metricsQuery.data
  const summary = metrics?.summary
  const errorRate = summary && summary.total > 0 ? (summary.errors / summary.total) * 100 : 0
  const days = Math.max(period.durationMs / 86_400_000, 1 / 24)

  const columns: Array<Column<ExternalApiRequest>> = [
    {
      key: 'created_at',
      header: 'Data/hora',
      render: (row) => <span className="whitespace-nowrap text-muted tabular-nums">{formatDateTimeWithSeconds(row.created_at)}</span>,
    },
    {
      key: 'service',
      header: 'Serviço',
      render: (row) => (
        <span className="flex items-center gap-2 whitespace-nowrap text-foreground">
          <span
            aria-hidden="true"
            className="size-2 shrink-0 rounded-full"
            style={{ backgroundColor: seriesColor(colorSlots[row.service] ?? 0) }}
          />
          {row.service_label}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge request={row} />,
    },
    {
      key: 'origin',
      header: 'Origem',
      render: (row) => (
        <div className="min-w-0 max-w-md">
          <p className="truncate font-mono text-xs text-foreground" title={row.origin ?? undefined}>{row.origin ?? '—'}</p>
          <p className="truncate text-xs text-muted" title={row.url}>{row.method} {row.url}</p>
        </div>
      ),
    },
    {
      key: 'tenant',
      header: 'Empresa',
      render: (row) => <span className="text-muted">{row.tenant?.name ?? '—'}</span>,
    },
    {
      key: 'duration',
      header: 'Duração',
      className: 'text-right',
      render: (row) => <span className="text-muted tabular-nums">{row.duration_ms !== null ? `${NUMBER.format(row.duration_ms)} ms` : '—'}</span>,
    },
  ]

  return (
    <Page>
      <PageHeader
        title="APIs externas"
        description="Requisições feitas pelo sistema a serviços externos, com request e response."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'APIs externas' }]}
        actions={
          <Button
            variant="secondary"
            size="sm"
            onClick={() => setAnchor(minuteAnchor())}
            loading={metricsQuery.isFetching || requestsQuery.isFetching}
          >
            <RefreshCw className="size-4" aria-hidden="true" />
            Atualizar
          </Button>
        }
      />

      <PageContent>
        <div className="flex flex-col gap-3 xl:flex-row xl:flex-wrap xl:items-center">
          <SegmentedControl<PeriodPreset>
            className="hidden sm:flex xl:w-[34rem] xl:shrink-0"
            value={preset}
            options={PERIOD_OPTIONS.map(({ value, label }) => ({ value, label }))}
            onChange={changePeriod}
          />
          <div className="sm:hidden">
            <Select
              aria-label="Período"
              value={preset}
              onChange={(event) => changePeriod(event.target.value as PeriodPreset)}
              options={PERIOD_OPTIONS.map((option) => ({ value: option.value, label: option.longLabel }))}
            />
          </div>
          {preset === 'custom' && (
            <DateRangeFilter
              from={customFrom}
              to={customTo}
              disableFuture
              onChange={(range) => update({ from: range.from || null, to: range.to || null, granularity: null })}
            />
          )}
          <div
            className="xl:w-(--segment-width) xl:shrink-0"
            style={{ '--segment-width': `${granularityOptions.length * 5.5}rem` } as CSSProperties}
          >
            <SegmentedControl<Granularity>
              value={granularity}
              options={granularityOptions}
              onChange={(value) => update({ granularity: value })}
            />
          </div>
          <Select
            aria-label="Filtrar por serviço"
            className="xl:w-64"
            value={service}
            onChange={(event) => update({ service: event.target.value || null })}
            options={[
              { value: '', label: 'Todos os serviços' },
              ...(servicesQuery.data ?? []).map((item) => ({
                value: item.key,
                label: `${item.provider_label} · ${item.label}`,
              })),
            ]}
          />
        </div>

        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          {summary ? (
            <>
              <Stat label="Requisições" value={NUMBER.format(summary.total)} hint="no período" />
              <Stat
                label="Média por dia"
                value={NUMBER.format(Math.round(summary.total / days))}
                hint={period.durationMs < 86_400_000 ? 'projeção pelo período' : undefined}
              />
              <Stat
                label="Erros"
                value={NUMBER.format(summary.errors)}
                hint={`${errorRate.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}% das requisições`}
              />
              <Stat
                label="Latência média"
                value={summary.avg_duration_ms !== null ? `${NUMBER.format(summary.avg_duration_ms)} ms` : '—'}
              />
            </>
          ) : (
            Array.from({ length: 4 }, (_, index) => <Skeleton key={index} className="h-[104px] rounded-xl" />)
          )}
        </div>

        <Card>
          <CardHeader
            title="Requisições ao longo do tempo"
            description={`Agrupadas por ${GRANULARITY_OPTIONS.find((option) => option.value === granularity)?.label.toLowerCase()} · fuso ${timezone}`}
          />
          <CardContent>
            {metricsQuery.isError ? (
              <EmptyState icon={Activity} title="Não foi possível carregar as métricas" />
            ) : !metrics ? (
              <Skeleton className="h-[320px] rounded-lg" />
            ) : metrics.series.length === 0 ? (
              <EmptyState icon={Activity} title="Nenhuma requisição no período" />
            ) : (
              <RequestsLineChart
                buckets={metrics.buckets}
                series={metrics.series}
                granularity={metrics.granularity}
                colorSlots={colorSlots}
              />
            )}
          </CardContent>
        </Card>

        {metrics && metrics.top_origins.length > 0 && (
          <Card>
            <CardHeader
              title="Principais origens"
              description="Rotas, comandos e jobs que mais disparam requisições no período."
            />
            <CardContent>
              <ul className="flex flex-col gap-2.5">
                {metrics.top_origins.map((item) => {
                  const share = summary && summary.total > 0 ? (item.total / summary.total) * 100 : 0
                  return (
                    <li key={`${item.origin}-${item.service}`} className="flex flex-col gap-1">
                      <div className="flex items-baseline justify-between gap-3 text-sm">
                        <button
                          type="button"
                          className="min-w-0 truncate text-left font-mono text-xs text-foreground hover:underline"
                          title="Filtrar requisições por esta origem"
                          onClick={() => setSearch(item.origin ?? '')}
                        >
                          {item.origin ?? '(sem origem)'}
                        </button>
                        <span className="shrink-0 text-muted">
                          <span className="font-semibold tabular-nums text-foreground">{NUMBER.format(item.total)}</span>
                          {' · '}
                          {item.label}
                        </span>
                      </div>
                      <div className="h-1.5 overflow-hidden rounded-full bg-surface-2">
                        <div
                          className="h-full rounded-full"
                          style={{ width: `${Math.max(share, 1)}%`, backgroundColor: seriesColor(colorSlots[item.service] ?? 0) }}
                        />
                      </div>
                    </li>
                  )
                })}
              </ul>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader
            title="Requisições"
            description="Clique em uma linha para ver request e response."
            actions={
              <Select
                aria-label="Filtrar por status"
                className="w-40"
                value={status}
                onChange={(event) => update({ status: event.target.value || null })}
                options={[
                  { value: '', label: 'Todos os status' },
                  { value: 'success', label: 'Sucesso' },
                  { value: 'error', label: 'Erro' },
                ]}
              />
            }
          />
          <CardContent className="flex flex-col gap-4">
            <SearchInput
              placeholder="Buscar por origem ou URL"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
            <DataTable
              caption="Requisições a APIs externas"
              columns={columns}
              rows={requestsQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              loading={requestsQuery.isPending}
              onRowClick={(row) => setSelectedId(row.id)}
              emptyState={<EmptyState icon={Activity} title="Nenhuma requisição encontrada" />}
            />
            {requestsQuery.data && (
              <Pagination
                meta={requestsQuery.data.meta}
                onPageChange={(next) => update({ page: next > 1 ? String(next) : null })}
              />
            )}
          </CardContent>
        </Card>
      </PageContent>

      <ExternalApiRequestModal requestId={selectedId} onClose={() => setSelectedId(null)} />
    </Page>
  )
}
