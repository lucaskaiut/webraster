import { Link } from 'react-router'
import { Badge, Card, CardContent, CardHeader, Page, PageContent, PageHeader } from '@/shared/design-system'
import { useSessionStore } from '@/shared/stores/session.store'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { Permission } from '@/shared/constants/permissions'
import { useAlertDashboardQuery } from '@/modules/alerts/hooks/useAlerts'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'

export default function DashboardPage() {
  const user = useSessionStore((state) => state.user)
  const { can } = usePermissions()
  const isUmbrella = useIsUmbrellaTenant()
  const showAlerts = !isUmbrella && can(Permission.ALERT_READ)
  const dashboard = useAlertDashboardQuery(showAlerts)

  return (
    <Page>
      <PageHeader
        title={`Olá, ${user?.name.split(' ')[0] ?? ''}`}
        description="Bem-vindo ao painel."
      />
      <PageContent>
        {showAlerts && (
          <div className="grid gap-4 lg:grid-cols-3">
            <Card>
              <CardContent>
                <p className="text-[13px] text-muted">Alertas hoje</p>
                <p className="mt-1 text-2xl font-semibold text-foreground">
                  {dashboard.data?.totals.today ?? '—'}
                </p>
              </CardContent>
            </Card>
            <Card>
              <CardContent>
                <p className="text-[13px] text-muted">Esta semana</p>
                <p className="mt-1 text-2xl font-semibold text-foreground">
                  {dashboard.data?.totals.week ?? '—'}
                </p>
              </CardContent>
            </Card>
            <Card>
              <CardContent>
                <p className="text-[13px] text-muted">Este mês</p>
                <p className="mt-1 text-2xl font-semibold text-foreground">
                  {dashboard.data?.totals.month ?? '—'}
                </p>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2">
              <CardHeader
                title="Por tipo (mês)"
                actions={
                  <Link to="/alerts" className="text-xs font-medium text-primary">
                    Ver alertas
                  </Link>
                }
              />
              <CardContent>
                <div className="flex flex-wrap gap-2">
                  {Object.entries(dashboard.data?.by_type ?? {}).length === 0 ? (
                    <p className="text-sm text-muted">Sem alertas no período.</p>
                  ) : (
                    Object.entries(dashboard.data?.by_type ?? {}).map(([type, total]) => (
                      <Badge key={type} variant="neutral">
                        {type}: {total}
                      </Badge>
                    ))
                  )}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader title="Críticos abertos" />
              <CardContent>
                <div className="space-y-2">
                  {(dashboard.data?.critical_open.length ?? 0) === 0 ? (
                    <p className="text-sm text-muted">Nenhum alerta crítico aberto.</p>
                  ) : (
                    dashboard.data!.critical_open.map((alert) => (
                      <div key={alert.id} className="text-sm">
                        <p className="font-medium text-foreground">{alert.title}</p>
                        <p className="text-[12px] text-muted">
                          {alert.vehicle?.plate ?? '—'} ·{' '}
                          {alert.occurred_at
                            ? new Date(alert.occurred_at).toLocaleString('pt-BR')
                            : ''}
                        </p>
                      </div>
                    ))
                  )}
                </div>
              </CardContent>
            </Card>
          </div>
        )}
      </PageContent>
    </Page>
  )
}
