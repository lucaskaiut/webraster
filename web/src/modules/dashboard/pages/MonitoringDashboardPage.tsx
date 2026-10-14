import { Link } from 'react-router'
import {
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import {
  Car,
  Cpu,
  Nfc,
  Package,
  Signal,
  SignalHigh,
  SignalLow,
  SignalZero,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useMonitoringDashboardQuery } from '../hooks/useDashboard'

function MetricCard({
  label,
  value,
  hint,
  icon: Icon,
  tone = 'neutral',
  to,
}: {
  label: string
  value: number | string
  hint?: string
  icon: LucideIcon
  tone?: 'neutral' | 'success' | 'warning' | 'danger'
  to?: string
}) {
  const card = (
    <Card className={cn('h-full', to && 'transition-shadow hover:shadow-raised')}>
      <CardContent className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-[13px] text-muted">{label}</p>
          <p className="mt-1 text-2xl font-semibold text-foreground">{value}</p>
          {hint && <p className="mt-0.5 text-[12px] text-subtle">{hint}</p>}
        </div>
        <span
          className={cn(
            'flex size-9 shrink-0 items-center justify-center rounded-lg',
            tone === 'neutral' && 'bg-surface-2 text-muted',
            tone === 'success' && 'bg-success-soft text-success',
            tone === 'warning' && 'bg-warning-soft text-warning',
            tone === 'danger' && 'bg-danger-soft text-danger',
          )}
        >
          <Icon className="size-4.5" aria-hidden="true" />
        </span>
      </CardContent>
    </Card>
  )

  if (!to) return card

  return (
    <Link to={to} className="block h-full rounded-xl">
      {card}
    </Link>
  )
}

export default function MonitoringDashboardPage() {
  const { canAny } = usePermissions()
  const enabled = canAny([
    Permission.TRACKING_READ,
    Permission.VEHICLE_READ,
    Permission.EQUIPMENT_READ,
  ])
  const query = useMonitoringDashboardQuery(enabled)

  return (
    <Page>
      <PageHeader
        title="Dashboard de monitoramento"
        description="Situação da frota e do estoque em tempo real."
        breadcrumb={[{ label: 'Dashboard' }, { label: 'Monitoramento' }]}
      />

      <PageContent>
        {!enabled && (
          <Card>
            <EmptyState
              icon={Car}
              title="Sem permissão de visualização"
              description="Solicite ao administrador acesso ao monitoramento, veículos ou equipamentos."
            />
          </Card>
        )}

        {enabled && query.isPending && (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 8 }).map((_, index) => (
              <Skeleton key={index} className="h-24 w-full rounded-xl" />
            ))}
          </div>
        )}

        {enabled && query.isError && (
          <Card>
            <EmptyState
              icon={Car}
              title="Não foi possível carregar os indicadores"
              description="Atualize a página e tente novamente."
            />
          </Card>
        )}

        {query.data && (
          <div className="space-y-6">
            <section className="space-y-3">
              <h2 className="text-sm font-semibold tracking-wide text-muted uppercase">Frota</h2>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <MetricCard
                  label="Veículos cadastrados"
                  value={query.data.vehicles.total}
                  icon={Car}
                  to="/vehicles"
                />
                <MetricCard
                  label="Veículos online"
                  value={query.data.vehicles.online}
                  icon={Signal}
                  tone="success"
                  to="/monitoring"
                />
                <MetricCard
                  label="Offline até 24 horas"
                  value={query.data.vehicles.offline_up_to_24h}
                  icon={SignalHigh}
                  tone="warning"
                  to="/monitoring"
                />
                <MetricCard
                  label="Offline de 24 a 36 horas"
                  value={query.data.vehicles.offline_24h_to_36h}
                  icon={SignalLow}
                  tone="warning"
                  to="/monitoring"
                />
                <MetricCard
                  label="Offline há mais de 36 horas"
                  value={query.data.vehicles.offline_over_36h}
                  icon={SignalZero}
                  tone="danger"
                  to="/monitoring"
                />
              </div>
            </section>

            {query.data.equipments && (
              <section className="space-y-3">
                <h2 className="text-sm font-semibold tracking-wide text-muted uppercase">
                  Estoque
                </h2>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  <MetricCard
                    label="Equipamentos ativos"
                    value={query.data.equipments.active}
                    icon={Cpu}
                    to="/equipments?is_active=1"
                  />
                  <MetricCard
                    label="Equipamentos em estoque"
                    value={query.data.equipments.in_stock}
                    icon={Package}
                    to="/equipments?available=1"
                  />
                  <MetricCard
                    label="Chips em estoque"
                    value={query.data.chips?.in_stock ?? 0}
                    hint="Equipamentos em estoque com número de linha"
                    icon={Nfc}
                    to="/equipments?available=1"
                  />
                </div>
              </section>
            )}
          </div>
        )}
      </PageContent>
    </Page>
  )
}
