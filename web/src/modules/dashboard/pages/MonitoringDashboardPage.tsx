import {
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
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
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { MetricCard } from '../components/MetricCard'
import { useMonitoringDashboardQuery } from '../hooks/useDashboard'

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
                  to="/vehicles?last_signal=offline_up_to_24h"
                />
                <MetricCard
                  label="Offline de 24 a 36 horas"
                  value={query.data.vehicles.offline_24h_to_36h}
                  icon={SignalLow}
                  tone="warning"
                  to="/vehicles?last_signal=offline_24h_to_36h"
                />
                <MetricCard
                  label="Offline há mais de 36 horas"
                  value={query.data.vehicles.offline_over_36h}
                  icon={SignalZero}
                  tone="danger"
                  to="/vehicles?last_signal=offline_over_36h"
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
