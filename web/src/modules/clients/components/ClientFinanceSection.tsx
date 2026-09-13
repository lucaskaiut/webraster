import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { CreditCard, RefreshCw } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Form,
  Loading,
  Section,
  TextField,
  type Column,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { formResolver } from '@/shared/utils/forms'
import type { FinanceBilling } from '@/shared/types/models'
import {
  useFinanceClientOverviewQuery,
  useReactivateFinanceSubscription,
  useUpdateFinanceSubscription,
} from '@/modules/finance/hooks/useFinance'
import {
  billingStatusBadgeVariant,
  billingStatusLabel,
  paymentMethodLabel,
  periodicityLabel,
  subscriptionStatusBadgeVariant,
  subscriptionStatusLabel,
} from '@/modules/finance/lib/labels'
import { toast } from '@/shared/stores/toast.store'

const dueSettingsSchema = z.object({
  due_day: z.coerce.number().int().min(1, 'Mínimo 1').max(28, 'Máximo 28'),
  next_billing_at: z.string().optional(),
})

type DueSettingsValues = z.infer<typeof dueSettingsSchema>

function toDateInput(value: string | null | undefined): string {
  if (!value) return ''
  return value.slice(0, 10)
}

function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 border-b border-border/60 py-2 last:border-0">
      <span className="text-muted">{label}</span>
      <span className="text-right text-foreground">{value}</span>
    </div>
  )
}

export function ClientFinanceSection({ clientId }: { clientId: string }) {
  const { can } = usePermissions()
  const overview = useFinanceClientOverviewQuery(
    can(Permission.FINANCE_SUBSCRIPTION_READ) ? clientId : undefined,
  )
  const reactivate = useReactivateFinanceSubscription()
  const updateSubscription = useUpdateFinanceSubscription()
  const [confirmReactivate, setConfirmReactivate] = useState(false)

  const subscription = overview.data?.subscription ?? null
  const openBilling = overview.data?.open_billing ?? null
  const billings = overview.data?.billings ?? []

  const dueForm = useForm<DueSettingsValues>({
    resolver: formResolver<DueSettingsValues>(dueSettingsSchema),
    defaultValues: {
      due_day: 10,
      next_billing_at: '',
    },
  })

  useEffect(() => {
    dueForm.reset({
      due_day: subscription?.due_day ?? 10,
      next_billing_at: toDateInput(subscription?.next_billing_at),
    })
  }, [subscription, dueForm])

  const columns: Array<Column<FinanceBilling>> = useMemo(
    () => [
      {
        key: 'code',
        header: 'Cobrança',
        render: (item) => (
          <Link
            to={`/finance/billings/${item.id}`}
            className="font-medium text-primary hover:underline"
          >
            {item.code}
          </Link>
        ),
      },
      {
        key: 'due_at',
        header: 'Vencimento',
        render: (item) => <span className="text-muted">{formatDate(item.due_at)}</span>,
      },
      {
        key: 'total',
        header: 'Valor',
        render: (item) => (
          <span className="text-foreground">{formatCurrency(item.total ?? item.amount)}</span>
        ),
      },
      {
        key: 'status',
        header: 'Status',
        render: (item) => (
          <Badge variant={billingStatusBadgeVariant(item.status)}>
            {item.status_label ?? billingStatusLabel(item.status)}
          </Badge>
        ),
      },
      {
        key: 'method',
        header: 'Método',
        render: (item) => (
          <span className="text-muted">
            {item.payment_method_label ?? paymentMethodLabel(item.payment_method)}
          </span>
        ),
      },
    ],
    [],
  )

  if (!can(Permission.FINANCE_SUBSCRIPTION_READ)) {
    return null
  }

  if (overview.isPending) {
    return (
      <Card>
        <CardContent>
          <Loading />
        </CardContent>
      </Card>
    )
  }

  if (overview.isError) {
    return (
      <Card>
        <CardHeader title="Assinatura" />
        <CardContent>
          <EmptyState
            icon={CreditCard}
            title="Não foi possível carregar o financeiro"
            description="Verifique suas permissões ou tente novamente."
          />
        </CardContent>
      </Card>
    )
  }

  const canEdit = can(Permission.FINANCE_SUBSCRIPTION_UPDATE)
  const canReactivate =
    canEdit &&
    !!subscription &&
    (subscription.status !== 'active' || !!openBilling)

  const saveDueSettings = async (values: DueSettingsValues) => {
    if (!subscription || !canEdit) return

    const next = values.next_billing_at || null
    const currentNext = toDateInput(subscription.next_billing_at) || null
    const dueChanged = values.due_day !== subscription.due_day
    const nextChanged = next !== currentNext

    if (!dueChanged && !nextChanged) {
      toast.success('Nenhuma alteração para salvar')
      return
    }

    await updateSubscription.mutateAsync({
      id: subscription.id,
      payload: {
        ...(dueChanged ? { due_day: values.due_day } : {}),
        ...(nextChanged ? { next_billing_at: next } : {}),
      },
    })
    void overview.refetch()
  }

  const planName = subscription?.plan_name || 'Pedido'
  const amountLabel =
    subscription != null ? formatCurrency(subscription.plan_price_cents / 100) : '—'
  const periodicity =
    subscription != null
      ? (subscription.plan_periodicity_label ?? periodicityLabel(subscription.plan_periodicity))
      : '—'

  if (!subscription) {
    return null
  }

  return (
    <>
      <Card>
        <CardHeader
          title="Recorrência"
          description="Status, vencimento e cobranças geradas a partir do pedido."
          actions={
            <div className="flex flex-wrap gap-2">
              {canReactivate && (
                <Button variant="secondary" onClick={() => setConfirmReactivate(true)}>
                  <RefreshCw className="size-4" />
                  Reativar assinatura
                </Button>
              )}
            </div>
          }
        />
        <CardContent className="space-y-8">
          <div className="grid gap-6 lg:grid-cols-2">
            <div className="space-y-1 text-sm">
              <Row label="Contrato" value={planName} />
              <Row
                label="Assinatura"
                value={
                  <Badge variant={subscriptionStatusBadgeVariant(subscription.status)}>
                    {subscription.status_label ?? subscriptionStatusLabel(subscription.status)}
                  </Badge>
                }
              />
              <Row label="Valor" value={amountLabel} />
              <Row label="Periodicidade" value={periodicity} />
            </div>
            <div className="space-y-1 text-sm">
              <Row
                label="Próxima cobrança"
                value={formatDate(subscription.next_billing_at) || '—'}
              />
              <Row
                label="Última cobrança"
                value={formatDate(subscription.last_billed_at) || '—'}
              />
              <Row label="Dia de vencimento" value={subscription.due_day} />
            </div>
          </div>

          {openBilling && (
            <Alert
              variant={openBilling.status === 'overdue' ? 'danger' : 'warning'}
              title={`Cobrança em aberto · ${openBilling.code}`}
            >
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={billingStatusBadgeVariant(openBilling.status)}>
                      {openBilling.status_label ?? billingStatusLabel(openBilling.status)}
                    </Badge>
                    <span>
                      Vencimento {formatDate(openBilling.due_at)} ·{' '}
                      {formatCurrency(openBilling.total ?? openBilling.amount)}
                      {openBilling.payment_method
                        ? ` · ${openBilling.payment_method_label ?? paymentMethodLabel(openBilling.payment_method)}`
                        : ''}
                    </span>
                  </div>
                  {(openBilling.invoice_url || openBilling.bank_slip_url) && (
                    <p>
                      {openBilling.invoice_url && (
                        <a
                          href={openBilling.invoice_url}
                          target="_blank"
                          rel="noreferrer"
                          className="underline underline-offset-2"
                        >
                          Abrir fatura
                        </a>
                      )}
                      {openBilling.invoice_url && openBilling.bank_slip_url ? ' · ' : null}
                      {openBilling.bank_slip_url && (
                        <a
                          href={openBilling.bank_slip_url}
                          target="_blank"
                          rel="noreferrer"
                          className="underline underline-offset-2"
                        >
                          Boleto
                        </a>
                      )}
                    </p>
                  )}
                </div>
                <ButtonLink to={`/finance/billings/${openBilling.id}`} variant="secondary">
                  Ver cobrança
                </ButtonLink>
              </div>
            </Alert>
          )}

          <Section
            title="Vencimento"
            description="Dia de vencimento e próxima data de cobrança da assinatura."
          >
            <Form form={dueForm} onSubmit={saveDueSettings} className="space-y-6">
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  name="due_day"
                  label="Dia de vencimento"
                  type="number"
                  min={1}
                  max={28}
                  hint="Dia do mês (1–28) usado nas cobranças."
                  disabled={!canEdit}
                />
                <TextField
                  name="next_billing_at"
                  label="Próxima cobrança"
                  type="date"
                  hint="Data da próxima geração de cobrança."
                  disabled={!canEdit}
                />
              </div>
              {canEdit && (
                <div className="flex justify-end">
                  <Button type="submit" loading={updateSubscription.isPending}>
                    Salvar vencimento
                  </Button>
                </div>
              )}
            </Form>
          </Section>

          <Section title="Histórico de cobranças" description="Últimas cobranças deste cliente.">
            <DataTable
              caption="Histórico de cobranças"
              columns={columns}
              rows={billings}
              rowKey={(item) => item.id}
              emptyState={
                <EmptyState
                  icon={CreditCard}
                  title="Nenhuma cobrança"
                  description="Ainda não há cobranças para este cliente."
                />
              }
            />
          </Section>
        </CardContent>
      </Card>

      <ConfirmDialog
        open={confirmReactivate}
        onClose={() => setConfirmReactivate(false)}
        onConfirm={() => {
          if (!subscription) return
          reactivate.mutate(subscription.id, {
            onSettled: () => setConfirmReactivate(false),
          })
        }}
        loading={reactivate.isPending}
        title="Reativar assinatura"
        description={
          <>
            Isso reativa a assinatura e libera os dispositivos do cliente, mesmo se houver cobrança
            em aberto ou inadimplência.
          </>
        }
        confirmLabel="Reativar"
      />
    </>
  )
}
