import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { BarChart3, BellRing, CalendarDays, ShieldAlert } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  SegmentedControl,
  Skeleton,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { useSessionStore } from '@/shared/stores/session.store'
import { cn } from '@/shared/utils/cn'
import { formatRelative, toLocalIsoDate } from '@/shared/utils/format'
import type { Alert, AlertDashboardStats, AlertSeverity, AlertStatus } from '@/shared/types/models'
import {
  ALERTS_POLL_INTERVAL_MS,
  useAcknowledgeAlert,
  useAlertDashboardQuery,
  useAlertsQuery,
  useResolveAlert,
} from '@/modules/alerts/hooks/useAlerts'
import {
  ALERT_SEVERITY_LABELS,
  ALERT_STATUS_LABELS,
  ALERT_TYPE_LABELS,
} from '@/modules/alerts/lib/alert-types'

type AlertSegment = 'open' | 'critical' | 'acknowledged' | 'resolved'

const SEGMENTS: Array<{ value: AlertSegment; label: string }> = [
  { value: 'open', label: 'Abertos' },
  { value: 'critical', label: 'Críticos' },
  { value: 'acknowledged', label: 'Reconhecidos' },
  { value: 'resolved', label: 'Resolvidos' },
]

const SEGMENT_PARAMS: Record<AlertSegment, { status?: string; severity?: string }> = {
  open: { status: 'open' },
  critical: { status: 'open', severity: 'critical' },
  acknowledged: { status: 'acknowledged' },
  resolved: { status: 'resolved' },
}

const SEVERITY_ORDER: AlertSeverity[] = ['critical', 'high', 'medium', 'low']

const SEVERITY_ACCENT: Record<AlertSeverity, string> = {
  critical: 'bg-danger',
  high: 'bg-warning',
  medium: 'bg-primary',
  low: 'bg-surface-3',
}

const SEVERITY_BAR: Record<AlertSeverity, string> = {
  critical: 'bg-danger',
  high: 'bg-warning',
  medium: 'bg-primary',
  low: 'bg-muted',
}

const SEVERITY_BADGE: Record<AlertSeverity, 'danger' | 'warning' | 'primary' | 'neutral'> = {
  critical: 'danger',
  high: 'warning',
  medium: 'primary',
  low: 'neutral',
}

const STATUS_BADGE: Record<AlertStatus, 'warning' | 'primary' | 'success'> = {
  open: 'warning',
  acknowledged: 'primary',
  resolved: 'success',
}

function KpiCard({
  to,
  label,
  value,
  icon: Icon,
  iconClassName,
}: {
  to: string
  label: string
  value: number | undefined
  icon: LucideIcon
  iconClassName: string
}) {
  return (
    <Link to={to} className="group rounded-xl">
      <Card className="h-full transition-colors group-hover:bg-surface-2">
        <CardContent className="flex items-center gap-4">
          <span className={cn('flex size-11 shrink-0 items-center justify-center rounded-xl', iconClassName)}>
            <Icon className="size-5" aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <p className="text-[13px] text-muted">{label}</p>
            <p className="mt-0.5 text-2xl font-semibold text-foreground">{value ?? '—'}</p>
          </div>
        </CardContent>
      </Card>
    </Link>
  )
}

function AlertRow({
  alert,
  canManage,
  acknowledging,
  resolving,
  onAcknowledge,
  onResolve,
  onOpen,
}: {
  alert: Alert
  canManage: boolean
  acknowledging: boolean
  resolving: boolean
  onAcknowledge: () => void
  onResolve: () => void
  onOpen: () => void
}) {
  const vehicleDescription = [alert.vehicle?.brand, alert.vehicle?.model].filter(Boolean).join(' ')

  return (
    <li className="flex items-stretch gap-3 rounded-xl bg-surface-2 px-3 py-3">
      <span
        className={cn('w-1 shrink-0 rounded-full', SEVERITY_ACCENT[alert.severity])}
        aria-hidden="true"
      />
      <button type="button" onClick={onOpen} className="min-w-0 flex-1 text-left">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <p className="truncate text-sm font-semibold text-foreground">{alert.title}</p>
          <Badge variant={SEVERITY_BADGE[alert.severity]}>
            {ALERT_SEVERITY_LABELS[alert.severity] ?? alert.severity}
          </Badge>
          <Badge variant={STATUS_BADGE[alert.status]}>
            {ALERT_STATUS_LABELS[alert.status] ?? alert.status}
          </Badge>
        </div>
        <p className="mt-1 truncate text-[13px] text-muted">
          {alert.vehicle?.plate ?? '—'}
          {vehicleDescription ? ` (${vehicleDescription})` : ''} ·{' '}
          {ALERT_TYPE_LABELS[alert.type] ?? alert.type_label ?? alert.type} ·{' '}
          {formatRelative(alert.occurred_at)}
        </p>
      </button>
      {canManage && alert.status !== 'resolved' && (
        <div className="flex shrink-0 items-center gap-1">
          {alert.status === 'open' && (
            <Button size="sm" variant="secondary" loading={acknowledging} onClick={onAcknowledge}>
              Reconhecer
            </Button>
          )}
          <Button size="sm" variant="ghost" loading={resolving} onClick={onResolve}>
            Resolver
          </Button>
        </div>
      )}
    </li>
  )
}

function BarList({
  items,
}: {
  items: Array<{ key: string; label: string; total: number; barClassName: string }>
}) {
  const max = Math.max(...items.map((item) => item.total), 1)

  return (
    <div className="space-y-3">
      {items.map((item) => (
        <div key={item.key}>
          <div className="flex items-center justify-between gap-2 text-sm">
            <span className="truncate text-foreground">{item.label}</span>
            <span className="font-medium text-muted">{item.total}</span>
          </div>
          <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-3">
            <div
              className={cn('h-full rounded-full', item.barClassName)}
              style={{ width: `${Math.max(4, Math.round((item.total / max) * 100))}%` }}
            />
          </div>
        </div>
      ))}
    </div>
  )
}

function LastSevenDays({ byDay }: { byDay: Record<string, number> }) {
  const days = useMemo(() => {
    const formatter = new Intl.DateTimeFormat('pt-BR', { weekday: 'short' })
    const today = new Date()

    return Array.from({ length: 7 }, (_, index) => {
      const date = new Date(today)
      date.setDate(today.getDate() - (6 - index))
      const key = toLocalIsoDate(date)

      return {
        key,
        label: formatter.format(date).replace('.', '').slice(0, 3),
        total: byDay[key] ?? 0,
      }
    })
  }, [byDay])

  const max = Math.max(...days.map((day) => day.total), 1)

  return (
    <div className="flex h-28 items-end gap-2">
      {days.map((day) => (
        <div key={day.key} className="flex h-full flex-1 flex-col items-center justify-end gap-1.5">
          <span className="text-[11px] font-medium text-muted">{day.total}</span>
          <div
            className="w-full rounded-md bg-primary/80"
            style={{ height: `${Math.max(4, Math.round((day.total / max) * 100))}%` }}
            title={`${day.total} alerta(s)`}
          />
          <span className="text-[11px] text-muted capitalize">{day.label}</span>
        </div>
      ))}
    </div>
  )
}

export default function DashboardPage() {
  const user = useSessionStore((state) => state.user)
  const navigate = useNavigate()
  const { can } = usePermissions()
  const isUmbrella = useIsUmbrellaTenant()
  const [segment, setSegment] = useState<AlertSegment>('open')

  const showAlerts = !isUmbrella && can(Permission.ALERT_READ)
  const canManage = can(Permission.ALERT_MANAGE)

  const dashboard = useAlertDashboardQuery(showAlerts)
  const alertsQuery = useAlertsQuery(
    {
      page: 1,
      per_page: 8,
      sort: 'desc',
      ...SEGMENT_PARAMS[segment],
    },
    { enabled: showAlerts, refetchInterval: ALERTS_POLL_INTERVAL_MS },
  )

  const acknowledge = useAcknowledgeAlert()
  const resolve = useResolveAlert()

  const stats: AlertDashboardStats | undefined = dashboard.data
  const alerts = alertsQuery.data?.data ?? []

  const severityItems = SEVERITY_ORDER.map((severity) => ({
    key: severity,
    label: ALERT_SEVERITY_LABELS[severity] ?? severity,
    total: stats?.by_severity[severity] ?? 0,
    barClassName: SEVERITY_BAR[severity],
  })).filter((item) => item.total > 0)

  const topVehicles = stats?.top_vehicles ?? []

  const typeItems = Object.entries(stats?.by_type ?? {})
    .sort(([, a], [, b]) => b - a)
    .map(([type, total]) => ({
      type,
      label: ALERT_TYPE_LABELS[type] ?? type,
      total,
    }))

  return (
    <Page>
      <PageHeader
        title={`Olá, ${user?.name.split(' ')[0] ?? ''}`}
        description={
          showAlerts
            ? 'Acompanhe os alertas dos veículos em tempo quase real.'
            : 'Bem-vindo ao painel.'
        }
      />
      <PageContent>
        {showAlerts && (
          <div className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <KpiCard
                to="/alerts?status=open"
                label="Abertos agora"
                value={stats?.open_total}
                icon={BellRing}
                iconClassName="bg-warning-soft text-warning"
              />
              <KpiCard
                to="/alerts?status=open&severity=critical"
                label="Críticos abertos"
                value={stats?.critical_open_total}
                icon={ShieldAlert}
                iconClassName="bg-danger-soft text-danger"
              />
              <KpiCard
                to="/alerts"
                label="Alertas hoje"
                value={stats?.totals.today}
                icon={CalendarDays}
                iconClassName="bg-primary-soft text-primary"
              />
              <KpiCard
                to="/alerts"
                label="Este mês"
                value={stats?.totals.month}
                icon={BarChart3}
                iconClassName="bg-surface-2 text-muted"
              />
            </div>

            <div className="grid gap-5 lg:grid-cols-3">
              <Card className="lg:col-span-2">
                <CardHeader
                  title="Alertas dos veículos"
                  description="Ocorrências recentes com ações rápidas."
                  actions={
                    <Link to="/alerts" className="text-xs font-medium text-primary">
                      Ver todos
                    </Link>
                  }
                />
                <CardContent className="space-y-4">
                  <SegmentedControl
                    value={segment}
                    options={SEGMENTS}
                    onChange={setSegment}
                    className="sm:max-w-md"
                  />

                  {alertsQuery.isPending ? (
                    <div className="space-y-2">
                      {Array.from({ length: 5 }).map((_, index) => (
                        <Skeleton key={index} className="h-16 w-full rounded-xl" />
                      ))}
                    </div>
                  ) : alerts.length === 0 ? (
                    <EmptyState
                      icon={BellRing}
                      title="Nenhum alerta neste filtro"
                      description="Assim que algo acontecer com os veículos, aparece aqui."
                      className="py-10"
                    />
                  ) : (
                    <ul className="space-y-2">
                      {alerts.map((alert) => (
                        <AlertRow
                          key={alert.id}
                          alert={alert}
                          canManage={canManage}
                          acknowledging={acknowledge.isPending && acknowledge.variables === alert.id}
                          resolving={resolve.isPending && resolve.variables === alert.id}
                          onAcknowledge={() => acknowledge.mutate(alert.id)}
                          onResolve={() => resolve.mutate(alert.id)}
                          onOpen={() => navigate(`/alerts?type=${alert.type}`)}
                        />
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>

              <div className="space-y-5">
                <Card>
                  <CardHeader
                    title="Veículos com alertas"
                    description="Mais ocorrências em aberto."
                  />
                  <CardContent>
                    {dashboard.isPending ? (
                      <Skeleton className="h-24 w-full" />
                    ) : topVehicles.length === 0 ? (
                      <p className="text-sm text-muted">Nenhum veículo com alertas em aberto.</p>
                    ) : (
                      <BarList
                        items={topVehicles.map((vehicle) => ({
                          key: vehicle.vehicle_id ?? vehicle.plate ?? String(vehicle.total),
                          label: vehicle.plate ?? '—',
                          total: vehicle.total,
                          barClassName: 'bg-primary',
                        }))}
                      />
                    )}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader
                    title="Abertos por severidade"
                    description="Distribuição atual dos alertas abertos."
                  />
                  <CardContent>
                    {dashboard.isPending ? (
                      <Skeleton className="h-24 w-full" />
                    ) : severityItems.length === 0 ? (
                      <p className="text-sm text-muted">Nenhum alerta aberto.</p>
                    ) : (
                      <BarList items={severityItems} />
                    )}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader
                    title="Últimos 7 dias"
                    description={`${stats?.totals.week ?? 0} alertas nesta semana.`}
                  />
                  <CardContent>
                    {dashboard.isPending ? (
                      <Skeleton className="h-28 w-full" />
                    ) : (
                      <LastSevenDays byDay={stats?.by_day ?? {}} />
                    )}
                  </CardContent>
                </Card>
              </div>
            </div>

            <Card>
              <CardHeader title="Por tipo (mês)" description="Volume do mês por tipo de alerta." />
              <CardContent>
                {typeItems.length === 0 ? (
                  <p className="text-sm text-muted">Sem alertas no período.</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {typeItems.map((item) => (
                      <Link key={item.type} to={`/alerts?type=${item.type}`}>
                        <Badge variant="neutral" className="transition-colors hover:bg-surface-3">
                          {item.label}: {item.total}
                        </Badge>
                      </Link>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        )}
      </PageContent>
    </Page>
  )
}
