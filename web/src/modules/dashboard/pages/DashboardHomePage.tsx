import { lazy } from 'react'
import { LayoutDashboard } from 'lucide-react'
import { Card, EmptyState, Page, PageContent, PageHeader } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useSessionStore } from '@/shared/stores/session.store'

const MonitoringDashboardPage = lazy(() => import('./MonitoringDashboardPage'))
const TenantsOverviewPage = lazy(() => import('@/modules/tenants/pages/TenantsOverviewPage'))

/**
 * Página inicial: o umbrella vê a visão geral das empresas do grupo; as
 * empresas filhas, o dashboard de monitoramento.
 */
export default function DashboardHomePage() {
  const isUmbrella = useIsUmbrellaTenant()
  const isMaster = useSessionStore((state) => state.isMaster)
  const { can } = usePermissions()

  if (!isUmbrella) {
    return <MonitoringDashboardPage />
  }

  if (isMaster && can(Permission.TENANT_READ)) {
    return <TenantsOverviewPage />
  }

  return (
    <Page>
      <PageHeader title="Dashboard" breadcrumb={[{ label: 'Dashboard' }]} />
      <PageContent>
        <Card>
          <EmptyState
            icon={LayoutDashboard}
            title="Bem-vindo"
            description="Use o menu lateral para acessar as áreas liberadas para o seu usuário."
          />
        </Card>
      </PageContent>
    </Page>
  )
}
