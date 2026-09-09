import { Card, CardContent, Loading, Page, PageContent, PageHeader } from '@/shared/design-system'
import { formatCurrency } from '@/shared/utils/format'
import { useFinanceDashboardQuery } from '../hooks/useFinance'

function MetricCard({ label, value }: { label: string; value: string | number }) {
  return (
    <Card>
      <CardContent>
        <p className="text-[13px] text-muted">{label}</p>
        <p className="mt-1 text-2xl font-semibold text-foreground">{value}</p>
      </CardContent>
    </Card>
  )
}

export default function FinanceDashboardPage() {
  const query = useFinanceDashboardQuery()

  return (
    <Page>
      <PageHeader
        title="Dashboard financeiro"
        description="Indicadores de receita, inadimplência e assinaturas."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Financeiro' }]}
      />
      <PageContent>
        {query.isPending && <Loading />}
        {query.data && (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <MetricCard label="MRR" value={formatCurrency(query.data.mrr)} />
            <MetricCard label="ARR" value={formatCurrency(query.data.arr)} />
            <MetricCard label="Receita do mês" value={formatCurrency(query.data.month_revenue_received)} />
            <MetricCard label="Esperado no mês" value={formatCurrency(query.data.month_expected)} />
            <MetricCard label="Em aberto" value={formatCurrency(query.data.open_amount)} />
            <MetricCard label="Clientes ativos" value={query.data.active_clients} />
            <MetricCard label="Contratos ativos" value={query.data.active_contracts} />
            <MetricCard label="Assinaturas ativas" value={query.data.active_subscriptions} />
            <MetricCard label="Clientes inadimplentes" value={query.data.delinquent_clients} />
          </div>
        )}
      </PageContent>
    </Page>
  )
}
