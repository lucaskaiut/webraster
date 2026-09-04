import { useState } from 'react'
import { CreditCard, RefreshCw, XCircle } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Section,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { formatCurrency, formatDateTime } from '@/shared/utils/format'
import {
  useCancelSubscription,
  useGatewaysQuery,
  usePlanCatalogQuery,
  useCreateSubscription,
  useReactivateSubscription,
  useSubscriptionQuery,
} from '../hooks/useBilling'
import type { Plan, Subscription } from '@/shared/types/models'

const statusVariant: Record<
  Subscription['status'],
  'success' | 'warning' | 'danger' | 'primary' | undefined
> = {
  ACTIVE: 'success',
  TRIALING: 'primary',
  PAST_DUE: 'warning',
  SUSPENDED: 'danger',
  CANCELLED: undefined,
}

const statusLabel: Record<Subscription['status'], string> = {
  ACTIVE: 'Ativa',
  TRIALING: 'Em trial',
  PAST_DUE: 'Em atraso',
  SUSPENDED: 'Suspensa',
  CANCELLED: 'Cancelada',
}

const eventLabel: Record<string, string> = {
  SUBSCRIPTION_CREATED: 'Assinatura criada',
  TRIAL_STARTED: 'Período de teste iniciado',
  TRIAL_ENDED: 'Período de teste encerrado',
  INVOICE_GENERATED: 'Cobrança gerada',
  PAYMENT_CONFIRMED: 'Pagamento confirmado',
  PAYMENT_FAILED: 'Pagamento falhou',
  SUBSCRIPTION_SUSPENDED: 'Assinatura suspensa',
  SUBSCRIPTION_REACTIVATED: 'Assinatura reativada',
  SUBSCRIPTION_CANCELLED: 'Assinatura cancelada',
}

export default function SubscriptionPage() {
  const { data: subscription, isLoading } = useSubscriptionQuery()
  const { data: catalog = [] } = usePlanCatalogQuery()
  const { data: gateways = [] } = useGatewaysQuery()
  const createSubscription = useCreateSubscription()
  const cancelSubscription = useCancelSubscription()
  const reactivateSubscription = useReactivateSubscription()
  const [selectedGateway, setSelectedGateway] = useState<string>('')
  const [selectedPlan, setSelectedPlan] = useState<Plan | null>(null)

  const activeGateway = selectedGateway || gateways[0]?.key || ''

  const subscribe = (plan: Plan) => {
    setSelectedPlan(plan)
    createSubscription.mutate(
      { planId: plan.id, paymentGateway: activeGateway || undefined },
      { onSettled: () => setSelectedPlan(null) },
    )
  }

  return (
    <Page>
      <PageHeader
        title="Assinatura"
        description="Status do plano atual, próxima cobrança e histórico de eventos."
      />

      <PageContent className="space-y-6">
        {!isLoading && !subscription && (
          <EmptyState
            icon={CreditCard}
            title="Nenhuma assinatura"
            description="Escolha um plano e a forma de pagamento para começar."
          />
        )}

        {subscription && (
          <>
            <Card>
              <CardContent className="space-y-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <p className="text-sm text-muted">Plano atual</p>
                    <h2 className="text-xl font-semibold text-foreground">
                      {subscription.plan?.name ?? '—'}
                    </h2>
                    <p className="mt-1 text-muted">
                      {formatCurrency(subscription.plan?.price)} · Próxima cobrança{' '}
                      {formatDateTime(subscription.next_billing_at)}
                    </p>
                  </div>
                  <Badge variant={statusVariant[subscription.status]}>
                    {statusLabel[subscription.status]}
                  </Badge>
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                  <Meta label="Início" value={formatDateTime(subscription.started_at)} />
                  <Meta label="Trial até" value={formatDateTime(subscription.trial_ends_at)} />
                  <Meta
                    label="Última cobrança"
                    value={formatDateTime(subscription.last_billed_at)}
                  />
                  <Meta label="Gateway" value={subscription.payment_gateway ?? 'Não definido'} />
                </div>

                <div className="flex flex-wrap gap-2">
                  <Can permission={Permission.SUBSCRIPTION_UPDATE}>
                    {subscription.status !== 'CANCELLED' && (
                      <Button
                        variant="secondary"
                        onClick={() => cancelSubscription.mutate()}
                        loading={cancelSubscription.isPending}
                      >
                        <XCircle className="size-4" />
                        Cancelar
                      </Button>
                    )}
                    {(subscription.status === 'CANCELLED' ||
                      subscription.status === 'SUSPENDED' ||
                      subscription.status === 'PAST_DUE') && (
                      <Button
                        onClick={() => reactivateSubscription.mutate()}
                        loading={reactivateSubscription.isPending}
                      >
                        <RefreshCw className="size-4" />
                        Reativar
                      </Button>
                    )}
                  </Can>
                  <ButtonLink to="/billing/invoices" variant="ghost">
                    Ver cobranças
                  </ButtonLink>
                </div>
              </CardContent>
            </Card>

            <Section title="Histórico de eventos">
              {(subscription.events ?? []).length === 0 ? (
                <p className="text-sm text-muted">Nenhum evento registrado.</p>
              ) : (
                <ul className="divide-y divide-surface-3">
                  {(subscription.events ?? []).map((event) => (
                    <li
                      key={event.id}
                      className="flex items-baseline justify-between gap-4 py-2.5 text-sm first:pt-0 last:pb-0"
                    >
                      <span className="text-foreground">
                        {eventLabel[event.event] ?? event.event}
                      </span>
                      <span className="shrink-0 text-[13px] text-muted">
                        {formatDateTime(event.created_at)}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </Section>
          </>
        )}

        {!subscription && catalog.length > 0 && (
          <>
            <Section title="Forma de pagamento (opcional)">
              <p className="mb-3 text-sm text-muted">
                Você também pode escolher o método depois, ao pagar a fatura.
              </p>
              <div className="flex flex-wrap gap-2">
                {gateways.map((gateway) => (
                  <button
                    key={gateway.key}
                    type="button"
                    onClick={() => setSelectedGateway(gateway.key)}
                    className={
                      activeGateway === gateway.key
                        ? 'rounded-lg border border-primary bg-primary/10 px-3 py-2 text-sm font-medium text-foreground'
                        : 'rounded-lg border border-border px-3 py-2 text-sm text-muted hover:bg-surface-2'
                    }
                  >
                    {gateway.label}
                  </button>
                ))}
              </div>
            </Section>

            <Section title="Planos disponíveis">
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {catalog.map((plan) => (
                  <Card key={plan.id}>
                    <CardContent className="space-y-3">
                      <div>
                        <h3 className="font-semibold text-foreground">{plan.name}</h3>
                        <p className="text-sm text-muted">{plan.description}</p>
                      </div>
                      <p className="text-lg font-semibold">{formatCurrency(plan.price)}</p>
                      <Can permission={Permission.SUBSCRIPTION_UPDATE}>
                        <Button
                          className="w-full"
                          loading={createSubscription.isPending && selectedPlan?.id === plan.id}
                          onClick={() => subscribe(plan)}
                        >
                          Assinar
                        </Button>
                      </Can>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </Section>
          </>
        )}
      </PageContent>
    </Page>
  )
}

function Meta({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg bg-surface-2 px-3 py-2">
      <p className="text-xs text-muted">{label}</p>
      <p className="text-sm font-medium text-foreground">{value}</p>
    </div>
  )
}
