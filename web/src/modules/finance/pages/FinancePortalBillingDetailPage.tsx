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
  Form,
  Loading,
  Page,
  PageContent,
  PageHeader,
  SelectField,
  TextField,
} from '@/shared/design-system'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { onlyDigits } from '@/shared/utils/document'
import { useFinancePortalBillingQuery, useFinancePortalPay } from '../hooks/useFinance'
import {
  PAYMENT_METHOD_OPTIONS,
  billingStatusBadgeVariant,
  billingStatusLabel,
  paymentMethodLabel,
} from '../lib/labels'
import { chargeBillingSchema, type ChargeBillingFormValues } from '../schemas/charge.schema'

function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 border-b border-border/60 py-2 last:border-0">
      <span className="text-muted">{label}</span>
      <span className="text-right text-foreground">{value}</span>
    </div>
  )
}

const OPEN_STATUSES = new Set(['pending', 'awaiting_payment', 'overdue'])

export default function FinancePortalBillingDetailPage() {
  const { id } = useParams()
  const query = useFinancePortalBillingQuery(id)
  const pay = useFinancePortalPay()
  const [copied, setCopied] = useState(false)

  const form = useForm<ChargeBillingFormValues>({
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

  const paymentMethod = useWatch({ control: form.control, name: 'payment_method' })

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
          title="Fatura não encontrada"
          breadcrumb={[{ label: 'Minhas faturas', to: '/finance/portal/billings' }]}
        />
      </Page>
    )
  }

  const canPay = OPEN_STATUSES.has(billing.status)

  const onPay = async (values: ChargeBillingFormValues) => {
    await pay.mutateAsync({
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
    void query.refetch()
  }

  const copyPix = async () => {
    if (!billing.pix_copy_paste) return
    await navigator.clipboard.writeText(billing.pix_copy_paste)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <Page>
      <PageHeader
        title={billing.code}
        description={`Vencimento ${formatDate(billing.due_at)}`}
        breadcrumb={[
          { label: 'Minhas faturas', to: '/finance/portal/billings' },
          { label: billing.code },
        ]}
      />
      <PageContent className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader title="Resumo" />
          <CardContent className="space-y-1 text-sm">
            <Row
              label="Status"
              value={
                <Badge variant={billingStatusBadgeVariant(billing.status)}>
                  {billing.status_label ?? billingStatusLabel(billing.status)}
                </Badge>
              }
            />
            <Row label="Total" value={formatCurrency(billing.total)} />
            <Row label="Vencimento" value={formatDate(billing.due_at)} />
            <Row
              label="Método"
              value={billing.payment_method_label ?? paymentMethodLabel(billing.payment_method)}
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Pagamento" />
          <CardContent className="space-y-4 text-sm">
            {billing.pix_qr_code && (
              <img
                src={`data:image/png;base64,${billing.pix_qr_code}`}
                alt="QR Code PIX"
                className="mx-auto size-48 rounded-md border border-border bg-white p-2"
              />
            )}
            {billing.pix_copy_paste && (
              <div className="space-y-2">
                <p className="break-all rounded-md bg-surface-2 p-2 font-mono text-xs">
                  {billing.pix_copy_paste}
                </p>
                <Button type="button" variant="secondary" size="sm" onClick={() => void copyPix()}>
                  {copied ? 'Copiado!' : 'Copiar PIX'}
                </Button>
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
                Abrir fatura no Asaas
              </a>
            )}

            {canPay && (
              <Form form={form} onSubmit={onPay} className="space-y-4 border-t border-border pt-4">
                <SelectField
                  name="payment_method"
                  label="Pagar com"
                  options={PAYMENT_METHOD_OPTIONS}
                />
                {paymentMethod === 'credit_card' && (
                  <div className="grid gap-3 sm:grid-cols-2">
                    <TextField
                      name="holderName"
                      label="Nome no cartão"
                      className="sm:col-span-2"
                    />
                    <TextField name="number" label="Número" className="sm:col-span-2" />
                    <TextField name="expiryMonth" label="Mês" placeholder="MM" />
                    <TextField name="expiryYear" label="Ano" placeholder="AAAA" />
                    <TextField name="ccv" label="CVV" />
                  </div>
                )}
                <Button type="submit" loading={pay.isPending}>
                  Pagar {formatCurrency(billing.total)}
                </Button>
              </Form>
            )}
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}
