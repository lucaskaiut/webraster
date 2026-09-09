import type { ReactNode } from 'react'
import {
  Badge,
  Card,
  CardContent,
  CardHeader,
  EmptyState,
  Loading,
  Page,
  PageContent,
  PageHeader,
} from '@/shared/design-system'
import { Repeat } from 'lucide-react'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { useFinancePortalSubscriptionQuery } from '../hooks/useFinance'
import {
  periodicityLabel,
  subscriptionStatusBadgeVariant,
  subscriptionStatusLabel,
} from '../lib/labels'

function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 border-b border-border/60 py-2 last:border-0">
      <span className="text-muted">{label}</span>
      <span className="text-right text-foreground">{value}</span>
    </div>
  )
}

export default function FinancePortalSubscriptionPage() {
  const query = useFinancePortalSubscriptionQuery()

  return (
    <Page>
      <PageHeader
        title="Minha assinatura"
        description="Detalhes do plano e próxima cobrança."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Assinatura' }]}
      />
      <PageContent>
        {query.isPending && <Loading />}
        {!query.isPending && !query.data && (
          <Card>
            <EmptyState
              icon={Repeat}
              title="Nenhuma assinatura"
              description="Você ainda não possui uma assinatura ativa."
            />
          </Card>
        )}
        {query.data && (
          <Card>
            <CardHeader title="Assinatura" />
            <CardContent className="space-y-1 text-sm">
              <Row
                label="Status"
                value={
                  <Badge variant={subscriptionStatusBadgeVariant(query.data.status)}>
                    {query.data.status_label ?? subscriptionStatusLabel(query.data.status)}
                  </Badge>
                }
              />
              <Row label="Cliente" value={query.data.client?.name ?? '—'} />
              <Row label="Contrato" value={query.data.contract?.code ?? '—'} />
              <Row label="Plano" value={query.data.contract?.plan?.name ?? '—'} />
              <Row
                label="Valor"
                value={
                  query.data.contract?.amount_cents != null
                    ? formatCurrency(query.data.contract.amount_cents / 100)
                    : '—'
                }
              />
              <Row
                label="Periodicidade"
                value={
                  query.data.periodicity_label ?? periodicityLabel(query.data.periodicity)
                }
              />
              <Row label="Próxima cobrança" value={formatDate(query.data.next_billing_at)} />
              <Row label="Última cobrança" value={formatDate(query.data.last_billing_at)} />
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}
