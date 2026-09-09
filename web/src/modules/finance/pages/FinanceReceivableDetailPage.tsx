import { useState, type ReactNode } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useParams } from 'react-router'
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
  useCancelFinanceReceivable,
  useChargeFinanceReceivable,
  useFinanceReceivableQuery,
  useMarkFinanceReceivableReceived,
} from '../hooks/useFinance'
import {
  PAYMENT_METHOD_OPTIONS,
  paymentMethodLabel,
  reaisToCents,
  receivableStatusBadgeVariant,
  receivableStatusLabel,
} from '../lib/labels'
import {
  chargeReceivableSchema,
  markReceivedSchema,
  type ChargeReceivableFormValues,
  type MarkReceivedFormValues,
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

export default function FinanceReceivableDetailPage() {
  const { id } = useParams()
  const { can } = usePermissions()
  const query = useFinanceReceivableQuery(id)
  const charge = useChargeFinanceReceivable()
  const cancel = useCancelFinanceReceivable()
  const markReceived = useMarkFinanceReceivableReceived()

  const [chargeOpen, setChargeOpen] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [receivedOpen, setReceivedOpen] = useState(false)

  const chargeForm = useForm<ChargeReceivableFormValues>({
    resolver: zodResolver(chargeReceivableSchema),
    defaultValues: {
      payment_method: 'pix',
      holderName: '',
      number: '',
      expiryMonth: '',
      expiryYear: '',
      ccv: '',
    },
  })

  const receivedForm = useForm<MarkReceivedFormValues>({
    resolver: zodResolver(markReceivedSchema),
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

  const receivable = query.data
  if (!receivable) {
    return (
      <Page>
        <PageHeader
          title="Cobrança não encontrada"
          breadcrumb={[{ label: 'Cobranças', to: '/finance/receivables' }]}
        />
      </Page>
    )
  }

  const isOpen = OPEN_STATUSES.has(receivable.status)
  const canCharge = can(Permission.FINANCE_RECEIVABLE_CHARGE) && isOpen
  const canUpdate = can(Permission.FINANCE_RECEIVABLE_UPDATE) && isOpen

  const onCharge = async (values: ChargeReceivableFormValues) => {
    await charge.mutateAsync({
      id: receivable.id,
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

  const onMarkReceived = async (values: MarkReceivedFormValues) => {
    const cents = values.paid_amount.trim()
      ? reaisToCents(values.paid_amount)
      : undefined
    await markReceived.mutateAsync({
      id: receivable.id,
      paid_amount_cents: cents,
    })
    setReceivedOpen(false)
    void query.refetch()
  }

  return (
    <Page>
      <PageHeader
        title={receivable.code}
        description={receivable.client?.name ?? 'Cobrança'}
        breadcrumb={[
          { label: 'Cobranças', to: '/finance/receivables' },
          { label: receivable.code },
        ]}
        actions={
          <div className="flex flex-wrap gap-2">
            {canCharge && (
              <Button onClick={() => setChargeOpen(true)}>Cobrar</Button>
            )}
            {canUpdate && (
              <Button variant="secondary" onClick={() => setReceivedOpen(true)}>
                Marcar recebido
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
                <Badge variant={receivableStatusBadgeVariant(receivable.status)}>
                  {receivable.status_label ?? receivableStatusLabel(receivable.status)}
                </Badge>
              }
            />
            <Row label="Cliente" value={receivable.client?.name ?? '—'} />
            <Row label="Contrato" value={receivable.contract?.code ?? '—'} />
            <Row label="Vencimento" value={formatDate(receivable.due_at)} />
            <Row label="Total" value={formatCurrency(receivable.total)} />
            <Row
              label="Método"
              value={
                receivable.payment_method_label ??
                paymentMethodLabel(receivable.payment_method)
              }
            />
            <Row label="Pago em" value={formatDateTime(receivable.paid_at)} />
            <Row
              label="Valor pago"
              value={
                receivable.paid_amount_cents != null
                  ? formatCurrency(receivable.paid_amount_cents / 100)
                  : '—'
              }
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Pagamento" />
          <CardContent className="space-y-3 text-sm">
            {receivable.pix_copy_paste && (
              <div>
                <p className="mb-1 text-muted">PIX copia e cola</p>
                <p className="break-all rounded-md bg-surface-2 p-2 font-mono text-xs">
                  {receivable.pix_copy_paste}
                </p>
              </div>
            )}
            {receivable.bank_slip_url && (
              <a
                href={receivable.bank_slip_url}
                target="_blank"
                rel="noreferrer"
                className="text-primary hover:underline"
              >
                Abrir boleto
              </a>
            )}
            {receivable.invoice_url && (
              <a
                href={receivable.invoice_url}
                target="_blank"
                rel="noreferrer"
                className="block text-primary hover:underline"
              >
                Abrir fatura
              </a>
            )}
            {!receivable.pix_copy_paste &&
              !receivable.bank_slip_url &&
              !receivable.invoice_url && (
                <p className="text-muted">Nenhum dado de pagamento gerado ainda.</p>
              )}
            {receivable.description && (
              <p className="whitespace-pre-wrap text-foreground">{receivable.description}</p>
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

      <Modal
        open={receivedOpen}
        onClose={() => setReceivedOpen(false)}
        title="Marcar como recebido"
      >
        <Form form={receivedForm} onSubmit={onMarkReceived} className="space-y-4">
          <TextField
            name="paid_amount"
            label="Valor recebido (R$)"
            type="number"
            step="0.01"
            hint="Deixe em branco para usar o total da cobrança."
          />
          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setReceivedOpen(false)}>
              Fechar
            </Button>
            <Button type="submit" loading={markReceived.isPending}>
              Confirmar
            </Button>
          </div>
        </Form>
      </Modal>

      <ConfirmDialog
        open={cancelOpen}
        onClose={() => setCancelOpen(false)}
        onConfirm={() => {
          cancel.mutate(receivable.id, {
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
            Cancelar a cobrança <strong>{receivable.code}</strong>?
          </>
        }
        confirmLabel="Cancelar cobrança"
      />
    </Page>
  )
}
