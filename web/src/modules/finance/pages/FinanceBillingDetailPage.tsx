import { useState, type ReactNode } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, useParams } from 'react-router'
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  ConfirmDialog,
  Form,
  Loading,
  Modal,
  Page,
  PageContent,
  PageHeader,
  SelectField,
  TextField,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatCurrency, formatDate, formatDateTime } from '@/shared/utils/format'
import { onlyDigits } from '@/shared/utils/document'
import {
  useCancelFinanceBilling,
  useChargeFinanceBilling,
  useFinanceBillingQuery,
  useMarkFinanceBillingPaid,
} from '../hooks/useFinance'
import {
  PAYMENT_METHOD_OPTIONS,
  billingStatusBadgeVariant,
  billingStatusLabel,
  paymentMethodLabel,
  reaisToCents,
} from '../lib/labels'
import {
  chargeBillingSchema,
  markPaidSchema,
  type ChargeBillingFormValues,
  type MarkPaidFormValues,
} from '../schemas/charge.schema'

function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 border-b border-border/60 py-2 last:border-0">
      <span className="text-muted">{label}</span>
      <span className="text-right text-foreground">{value}</span>
    </div>
  )
}

const OPEN_STATUSES = new Set(['pending', 'awaiting_payment', 'overdue'])

export default function FinanceBillingDetailPage() {
  const { id } = useParams()
  const { can } = usePermissions()
  const query = useFinanceBillingQuery(id)
  const charge = useChargeFinanceBilling()
  const cancel = useCancelFinanceBilling()
  const markPaid = useMarkFinanceBillingPaid()

  const [chargeOpen, setChargeOpen] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [paidOpen, setPaidOpen] = useState(false)

  const chargeForm = useForm<ChargeBillingFormValues>({
    resolver: zodResolver(chargeBillingSchema),
    defaultValues: {
      payment_method: 'pix',
      holderName: '',
      number: '',
      expiryMonth: '',
      expiryYear: '',
      ccv: '',
    },
  })

  const paidForm = useForm<MarkPaidFormValues>({
    resolver: zodResolver(markPaidSchema),
    defaultValues: { paid_amount: '' },
  })

  const paymentMethod = useWatch({ control: chargeForm.control, name: 'payment_method' })

  if (query.isLoading) {
    return (
      <Page>
        <PageContent>
          <Loading />
        </PageContent>
      </Page>
    )
  }

  const billing = query.data
  if (!billing) {
    return (
      <Page>
        <PageHeader
          title="Cobrança não encontrada"
          breadcrumb={[{ label: 'Cobranças', to: '/finance/billings' }]}
        />
      </Page>
    )
  }

  const isOpen = OPEN_STATUSES.has(billing.status)
  const canCharge = can(Permission.FINANCE_BILLING_CHARGE) && isOpen
  const canUpdate = can(Permission.FINANCE_BILLING_UPDATE) && isOpen

  const onCharge = async (values: ChargeBillingFormValues) => {
    await charge.mutateAsync({
      id: billing.id,
      payload: {
        payment_method: values.payment_method,
        ...(values.payment_method === 'credit_card'
          ? {
              credit_card: {
                holderName: values.holderName,
                number: onlyDigits(values.number),
                expiryMonth: values.expiryMonth,
                expiryYear: values.expiryYear,
                ccv: values.ccv,
              },
            }
          : {}),
      },
    })
    setChargeOpen(false)
    void query.refetch()
  }

  const onMarkPaid = async (values: MarkPaidFormValues) => {
    const cents = values.paid_amount.trim() ? reaisToCents(values.paid_amount) : undefined
    await markPaid.mutateAsync({
      id: billing.id,
      paid_amount_cents: cents,
    })
    setPaidOpen(false)
    void query.refetch()
  }

  return (
    <Page>
      <PageHeader
        title={billing.code}
        description={billing.client?.name ?? 'Cobrança'}
        breadcrumb={[
          { label: 'Cobranças', to: '/finance/billings' },
          { label: billing.code },
        ]}
        actions={
          <div className="flex flex-wrap gap-2">
            {canCharge && <Button onClick={() => setChargeOpen(true)}>Cobrar</Button>}
            {canUpdate && (
              <Button variant="secondary" onClick={() => setPaidOpen(true)}>
                Marcar pago
              </Button>
            )}
            {canUpdate && (
              <Button variant="secondary" onClick={() => setCancelOpen(true)}>
                Cancelar
              </Button>
            )}
          </div>
        }
      />
      <PageContent className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader title="Detalhes" />
          <CardContent className="space-y-1 text-sm">
            <Row
              label="Status"
              value={
                <Badge variant={billingStatusBadgeVariant(billing.status)}>
                  {billing.status_label ?? billingStatusLabel(billing.status)}
                </Badge>
              }
            />
            <Row
              label="Cliente"
              value={
                billing.client_id ? (
                  <Link
                    to={`/clients/${billing.client_id}/edit?tab=assinatura`}
                    className="text-primary hover:underline"
                  >
                    {billing.client?.name ?? '—'}
                  </Link>
                ) : (
                  (billing.client?.name ?? '—')
                )
              }
            />
            <Row label="Vencimento" value={formatDate(billing.due_at)} />
            <Row label="Total" value={formatCurrency(billing.total)} />
            <Row
              label="Método"
              value={billing.payment_method_label ?? paymentMethodLabel(billing.payment_method)}
            />
            <Row label="Pago em" value={formatDateTime(billing.paid_at)} />
            <Row
              label="Valor pago"
              value={
                billing.paid_amount_cents != null
                  ? formatCurrency(billing.paid_amount_cents / 100)
                  : '—'
              }
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Pagamento" />
          <CardContent className="space-y-3 text-sm">
            {billing.pix_copy_paste && (
              <div>
                <p className="mb-1 text-muted">PIX copia e cola</p>
                <p className="break-all rounded-md bg-surface-2 p-2 font-mono text-xs">
                  {billing.pix_copy_paste}
                </p>
              </div>
            )}
            {billing.bank_slip_url && (
              <a
                href={billing.bank_slip_url}
                target="_blank"
                rel="noreferrer"
                className="text-primary hover:underline"
              >
                Abrir boleto
              </a>
            )}
            {billing.invoice_url && (
              <a
                href={billing.invoice_url}
                target="_blank"
                rel="noreferrer"
                className="block text-primary hover:underline"
              >
                Abrir fatura
              </a>
            )}
            {!billing.pix_copy_paste && !billing.bank_slip_url && !billing.invoice_url && (
              <p className="text-muted">Nenhum dado de pagamento gerado ainda.</p>
            )}
            {billing.description && (
              <p className="whitespace-pre-wrap text-foreground">{billing.description}</p>
            )}
          </CardContent>
        </Card>
      </PageContent>

      <Modal open={chargeOpen} onClose={() => setChargeOpen(false)} title="Cobrar via Asaas">
        <Form form={chargeForm} onSubmit={onCharge} className="space-y-4">
          <SelectField
            name="payment_method"
            label="Método de pagamento"
            options={PAYMENT_METHOD_OPTIONS}
          />
          {paymentMethod === 'credit_card' && (
            <div className="grid gap-3 sm:grid-cols-2">
              <TextField name="holderName" label="Nome no cartão" className="sm:col-span-2" />
              <TextField name="number" label="Número" className="sm:col-span-2" />
              <TextField name="expiryMonth" label="Mês" placeholder="MM" />
              <TextField name="expiryYear" label="Ano" placeholder="AAAA" />
              <TextField name="ccv" label="CVV" />
            </div>
          )}
          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setChargeOpen(false)}>
              Fechar
            </Button>
            <Button type="submit" loading={charge.isPending}>
              Enviar cobrança
            </Button>
          </div>
        </Form>
      </Modal>

      <Modal open={paidOpen} onClose={() => setPaidOpen(false)} title="Marcar como pago">
        <Form form={paidForm} onSubmit={onMarkPaid} className="space-y-4">
          <TextField
            name="paid_amount"
            label="Valor pago (R$)"
            type="number"
            step="0.01"
            hint="Deixe em branco para usar o total da cobrança."
          />
          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setPaidOpen(false)}>
              Fechar
            </Button>
            <Button type="submit" loading={markPaid.isPending}>
              Confirmar
            </Button>
          </div>
        </Form>
      </Modal>

      <ConfirmDialog
        open={cancelOpen}
        onClose={() => setCancelOpen(false)}
        onConfirm={() => {
          cancel.mutate(billing.id, {
            onSuccess: () => {
              setCancelOpen(false)
              void query.refetch()
            },
          })
        }}
        loading={cancel.isPending}
        title="Cancelar cobrança"
        description={
          <>
            Cancelar a cobrança <strong>{billing.code}</strong>?
          </>
        }
        confirmLabel="Cancelar cobrança"
      />
    </Page>
  )
}
