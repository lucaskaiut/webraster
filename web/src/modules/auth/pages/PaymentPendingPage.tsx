import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { Check, CheckCircle2, Copy, ExternalLink, Loader2, RefreshCw, Zap } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  Loading,
  RadioGroup,
  ThemeToggle,
} from '@/shared/design-system'
import { parseApiError } from '@/shared/api/errors'
import { formatCurrency, formatDateTime } from '@/shared/utils/format'
import { toast } from '@/shared/stores/toast.store'
import { checkoutService } from '../services/checkout.service'
import type { PaymentMethodOption } from '../store/register-checkout.store'
import type { Invoice } from '@/shared/types/models'
import { CreditCardPaymentForm } from '../components/payment/CreditCardPaymentForm'

type InvoiceStatus = Invoice['status']

const STATUS_CONFIG: Record<
  InvoiceStatus,
  {
    label: string
    variant: 'warning' | 'primary' | 'success' | 'danger' | 'neutral'
    title: string
    description: string
  }
> = {
  PENDING: {
    label: 'Aguardando pagamento',
    variant: 'warning',
    title: 'Aguardando pagamento',
    description: 'Conclua o pagamento com o método escolhido para liberar o acesso.',
  },
  PROCESSING: {
    label: 'Confirmando transação',
    variant: 'primary',
    title: 'Pagamento identificado',
    description: 'Estamos confirmando a transação. Isso costuma levar poucos instantes.',
  },
  PAID: {
    label: 'Pagamento confirmado',
    variant: 'success',
    title: 'Pagamento confirmado',
    description: 'Seu acesso à plataforma já está liberado.',
  },
  EXPIRED: {
    label: 'Cobrança expirada',
    variant: 'danger',
    title: 'Cobrança expirada',
    description: 'O prazo desta cobrança acabou. Acesse assinatura para regularizar.',
  },
  FAILED: {
    label: 'Pagamento falhou',
    variant: 'danger',
    title: 'Não foi possível confirmar o pagamento',
    description: 'Tente novamente ou escolha outro método na área de assinatura.',
  },
  CANCELLED: {
    label: 'Cancelada',
    variant: 'neutral',
    title: 'Cobrança cancelada',
    description: 'Esta cobrança não está mais disponível.',
  },
}

const METHOD_LABELS: Record<string, string> = {
  pix: 'PIX',
  credit_card: 'Cartão de crédito',
  boleto: 'Boleto',
}

export default function PaymentPendingPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const invoiceId = params.get('invoice') ?? ''
  const [invoice, setInvoice] = useState<Invoice | null>(null)
  const [methods, setMethods] = useState<PaymentMethodOption[]>([])
  const [selectedMethodId, setSelectedMethodId] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [checking, setChecking] = useState(false)
  const [initiating, setInitiating] = useState(false)
  const [copied, setCopied] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [now, setNow] = useState(() => Date.now())

  const awaitingMethod =
    Boolean(invoice?.awaiting_payment_method) ||
    (invoice?.status === 'PENDING' && !invoice.external_id && !invoice.pix_code)

  const selectedMethod = methods.find((method) => method.id === selectedMethodId) ?? null
  const isCreditCard = selectedMethod?.payment_method === 'credit_card'

  const remaining = useMemo(() => {
    if (!invoice?.expires_at) return null
    const ms = new Date(invoice.expires_at).getTime() - now
    if (ms <= 0) return 'Expirado'
    const minutes = Math.floor(ms / 60000)
    const seconds = Math.floor((ms % 60000) / 1000)
    return `${minutes}m ${seconds.toString().padStart(2, '0')}s`
  }, [invoice?.expires_at, now])

  const loadInvoice = useCallback(
    async (opts?: { manual?: boolean }) => {
      if (!invoiceId) return

      if (opts?.manual) setChecking(true)

      try {
        const data = await checkoutService.getInvoice(invoiceId)
        setInvoice(data)
        setError(null)

        if (data.status === 'PAID') {
          toast.success('Pagamento confirmado', 'Sua assinatura está ativa.')
          navigate('/dashboard', { replace: true })
          return
        }

        if (opts?.manual) {
          if (data.status === 'PROCESSING') {
            toast.success('Pagamento identificado', 'Confirmando a transação…')
          } else if (data.status === 'PENDING' && data.pix_code) {
            toast.info(
              'Ainda não encontramos o pagamento',
              'Assim que o PIX for confirmado, liberamos o acesso.',
            )
          } else if (data.status === 'PENDING') {
            toast.info(
              'Ainda não encontramos o pagamento',
              'Se já pagou, aguarde alguns instantes e tente de novo.',
            )
          }
        }
      } catch {
        if (opts?.manual) {
          toast.error('Falha ao verificar', 'Tente novamente em instantes.')
        } else {
          setError('Não foi possível carregar a cobrança.')
        }
      } finally {
        setLoading(false)
        setChecking(false)
      }
    },
    [invoiceId, navigate],
  )

  useEffect(() => {
    if (!invoiceId) {
      setError('Cobrança não encontrada.')
      setLoading(false)
      return
    }

    void loadInvoice()
    void checkoutService.listPaymentMethods().then((items) => {
      setMethods(items)
      if (items[0]) setSelectedMethodId(items[0].id)
    })

    const timer = window.setInterval(() => void loadInvoice(), 15000)
    return () => window.clearInterval(timer)
  }, [invoiceId, loadInvoice])

  useEffect(() => {
    if (!invoice?.expires_at || invoice.status !== 'PENDING') return
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [invoice?.expires_at, invoice?.status])

  useEffect(() => {
    if (!copied) return
    const timer = window.setTimeout(() => setCopied(false), 2000)
    return () => window.clearTimeout(timer)
  }, [copied])

  const copyPix = async () => {
    if (!invoice?.pix_code) return
    await navigator.clipboard.writeText(invoice.pix_code)
    setCopied(true)
    toast.success('PIX copiado', 'Cole no aplicativo do seu banco.')
  }

  const startPayment = async (paymentData: Record<string, unknown> = {}) => {
    if (!invoice || !selectedMethodId) return

    setInitiating(true)
    try {
      const paid = await checkoutService.payInvoice(invoice.id, selectedMethodId, paymentData)
      setInvoice(paid)

      if (paid.status === 'PAID') {
        toast.success('Pagamento confirmado', 'Sua assinatura está ativa.')
        navigate('/dashboard', { replace: true })
        return
      }

      if (paid.payment_method === 'credit_card' && !paid.pix_code) {
        if (paid.status === 'PROCESSING') {
          toast.success('Pagamento em análise', 'Aguarde a confirmação da operadora.')
          return
        }

        if (paid.invoice_url) {
          toast.success('Cobrança gerada', 'Você pode concluir o pagamento na fatura segura.')
          return
        }
      }

      toast.success('Cobrança gerada', 'Conclua o pagamento com o método escolhido.')
    } catch (err) {
      const apiError = parseApiError(err)
      const detail =
        Object.values(apiError.fieldErrors).flat()[0] ?? apiError.message
      toast.error('Não foi possível processar o pagamento', detail)
    } finally {
      setInitiating(false)
    }
  }

  const status = invoice ? STATUS_CONFIG[invoice.status] : null
  const hasGatewayCharge = Boolean(invoice?.pix_code || invoice?.external_id)
  const isAwaitingPayment =
    (invoice?.status === 'PENDING' || invoice?.status === 'PROCESSING') && hasGatewayCharge
  const isTerminal =
    invoice?.status === 'EXPIRED' ||
    invoice?.status === 'FAILED' ||
    invoice?.status === 'CANCELLED'
  const showCreditCardInvoice =
    isAwaitingPayment &&
    Boolean(invoice?.invoice_url) &&
    invoice?.payment_method === 'credit_card'

  return (
    <div className="relative flex min-h-dvh flex-col items-center justify-center px-4 py-10">
      <div className="absolute top-4 right-4">
        <ThemeToggle />
      </div>

      <div className="mb-8 flex items-center gap-2.5">
        <span className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-raised">
          <Zap className="size-5.5" aria-hidden="true" />
        </span>
        <span className="text-lg font-semibold tracking-tight text-foreground">Nox</span>
      </div>

      {loading ? (
        <Card className="w-full max-w-md shadow-raised">
          <CardContent className="flex flex-col items-center gap-3 p-10">
            <Loading />
            <p className="text-sm text-muted">Carregando sua cobrança…</p>
          </CardContent>
        </Card>
      ) : error || !invoice || !status ? (
        <Card className="w-full max-w-md shadow-raised">
          <CardContent className="space-y-5 p-8">
            <Alert variant="danger" title={error ?? 'Cobrança indisponível'} />
            <ButtonLink to="/dashboard" className="w-full">
              Ir para o painel
            </ButtonLink>
          </CardContent>
        </Card>
      ) : (
        <Card className="w-full max-w-3xl shadow-raised">
          <CardContent className="space-y-8 p-6 sm:p-8 lg:p-10">
            <header className="mx-auto max-w-xl text-center">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground sm:text-[1.75rem]">
                {awaitingMethod ? 'Escolha como pagar' : 'Pagamento'}
              </h1>
              <p className="mt-2 text-sm leading-relaxed text-muted sm:text-[0.9375rem]">
                {awaitingMethod
                  ? 'Selecione o método de pagamento para gerar a cobrança e liberar sua assinatura.'
                  : 'Finalize sua assinatura e tenha acesso imediato após a confirmação do pagamento.'}
              </p>
            </header>

            {(invoice.status === 'PROCESSING' || invoice.status === 'PAID') && (
              <Alert
                variant={invoice.status === 'PAID' ? 'success' : 'info'}
                title={status.title}
              >
                {status.description}
              </Alert>
            )}

            {isTerminal && (
              <Alert variant="danger" title={status.title}>
                {status.description}
              </Alert>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <InfoCard label="Valor" value={formatCurrency(invoice.amount)} emphasize />
              <InfoCard
                label="Status"
                value={
                  <Badge variant={status.variant} className="gap-1.5">
                    <span className="size-1.5 rounded-full bg-current" aria-hidden="true" />
                    {awaitingMethod ? 'Aguardando método' : status.label}
                  </Badge>
                }
              />
              <InfoCard label="Vencimento" value={formatDateTime(invoice.due_date)} />
              <InfoCard
                label="Expiração"
                value={remaining ?? formatDateTime(invoice.expires_at)}
              />
            </div>

            {awaitingMethod && !isTerminal && (
              <div className="space-y-5">
                <RadioGroup
                  name="payment_gateway"
                  aria-label="Métodos de pagamento"
                  value={selectedMethodId}
                  onChange={setSelectedMethodId}
                  options={methods.map((method) => ({
                    value: method.id,
                    label: method.name,
                    description: METHOD_LABELS[method.payment_method] ?? method.payment_method,
                  }))}
                />

                {isCreditCard ? (
                  <CreditCardPaymentForm
                    amount={invoice.amount}
                    loading={initiating}
                    onSubmit={(paymentData) => startPayment(paymentData)}
                  />
                ) : (
                  <Button
                    onClick={() => void startPayment()}
                    loading={initiating}
                    disabled={!selectedMethodId}
                    className="w-full sm:w-auto sm:min-w-64"
                  >
                    Continuar para pagamento
                  </Button>
                )}
              </div>
            )}

            {isAwaitingPayment && invoice.pix_qrcode && (
              <div className="flex flex-col items-center gap-3">
                <div className="rounded-2xl bg-white p-4 shadow-raised">
                  <img
                    src={invoice.pix_qrcode}
                    alt="QR Code PIX para pagamento"
                    className="size-56 sm:size-64"
                  />
                </div>
                <p className="max-w-[16rem] text-center text-xs text-muted">
                  Abra o app do seu banco e escaneie o código acima.
                </p>
              </div>
            )}

            {showCreditCardInvoice && invoice.invoice_url && (
              <div className="space-y-3 rounded-xl bg-surface-2 px-4 py-4">
                <p className="text-sm font-medium text-foreground">
                  Conclua o pagamento na fatura segura do Asaas.
                </p>
                <p className="text-sm text-muted">
                  Se a captura não foi concluída aqui, você pode informar o cartão diretamente na
                  fatura.
                </p>
                <Button
                  variant="secondary"
                  className="w-full sm:w-auto"
                  onClick={() => window.open(invoice.invoice_url!, '_blank', 'noopener,noreferrer')}
                >
                  <ExternalLink className="size-4" aria-hidden="true" />
                  Abrir fatura Asaas
                </Button>
              </div>
            )}

            {invoice.status === 'PAID' && (
              <div className="flex flex-col items-center gap-3 py-6 text-center">
                <span className="flex size-14 items-center justify-center rounded-2xl bg-success-soft text-success">
                  <CheckCircle2 className="size-7" aria-hidden="true" />
                </span>
                <p className="text-sm text-muted">Redirecionando para o painel…</p>
              </div>
            )}

            {isAwaitingPayment && invoice.pix_code && (
              <div className="space-y-3">
                <div>
                  <p className="text-xs font-medium tracking-wide text-muted uppercase">
                    PIX copia e cola
                  </p>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-stretch">
                  <code className="block min-h-12 flex-1 break-all rounded-xl bg-surface-2 px-4 py-3 font-mono text-xs leading-relaxed text-foreground sm:text-[13px]">
                    {invoice.pix_code}
                  </code>
                  <Button
                    variant={copied ? 'secondary' : 'primary'}
                    onClick={copyPix}
                    className="shrink-0 sm:min-w-32"
                    aria-live="polite"
                  >
                    {copied ? (
                      <>
                        <Check className="size-4" aria-hidden="true" />
                        Copiado
                      </>
                    ) : (
                      <>
                        <Copy className="size-4" aria-hidden="true" />
                        Copiar
                      </>
                    )}
                  </Button>
                </div>
              </div>
            )}

            {isAwaitingPayment && (
              <div className="rounded-xl bg-surface-2 px-4 py-3.5 text-center sm:text-left">
                <p className="text-sm font-medium text-foreground">
                  Estamos verificando seu pagamento automaticamente.
                </p>
                <p className="mt-1 text-sm text-muted">
                  A confirmação acontece em poucos instantes após o pagamento ser realizado.
                </p>
              </div>
            )}

            <div className="flex flex-col items-stretch gap-3 sm:items-center">
              {isAwaitingPayment && (
                <Button
                  onClick={() => void loadInvoice({ manual: true })}
                  loading={checking}
                  className="w-full sm:w-auto sm:min-w-64"
                >
                  {!checking && <RefreshCw className="size-4" aria-hidden="true" />}
                  Já realizei o pagamento
                </Button>
              )}

              {invoice.status === 'PROCESSING' && (
                <p className="flex items-center justify-center gap-2 text-sm text-muted">
                  <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                  Confirmando transação…
                </p>
              )}

              {isTerminal && (
                <ButtonLink to="/billing/subscription" className="w-full sm:w-auto sm:min-w-64">
                  Ir para assinatura
                </ButtonLink>
              )}

              <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 pt-1">
                <ButtonLink to="/dashboard" variant="ghost" size="sm">
                  Ir para o painel
                </ButtonLink>
                <Link
                  to="/billing/invoices"
                  className="text-sm font-medium text-primary hover:text-primary-hover"
                >
                  Ver cobranças
                </Link>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      <p className="mt-8 text-xs text-subtle">
        © {new Date().getFullYear()} Nox — Pagamento seguro
      </p>
    </div>
  )
}

function InfoCard({
  label,
  value,
  emphasize = false,
}: {
  label: string
  value: ReactNode
  emphasize?: boolean
}) {
  return (
    <div className="rounded-xl bg-surface px-4 py-3.5 shadow-card">
      <p className="text-xs font-medium tracking-wide text-muted uppercase">{label}</p>
      <div
        className={
          emphasize
            ? 'mt-1.5 text-xl font-semibold tracking-tight text-foreground'
            : 'mt-1.5 text-sm font-medium text-foreground'
        }
      >
        {value}
      </div>
    </div>
  )
}
