import type {
  BillingPeriodicity,
  FinanceBillingStatus,
  FinancePaymentMethod,
  FinanceReportType,
  FinanceSubscriptionStatus,
} from '@/shared/types/models'

export const PERIODICITY_OPTIONS: Array<{ value: BillingPeriodicity; label: string }> = [
  { value: 'monthly', label: 'Mensal' },
  { value: 'bimonthly', label: 'Bimestral' },
  { value: 'quarterly', label: 'Trimestral' },
  { value: 'semiannual', label: 'Semestral' },
  { value: 'annual', label: 'Anual' },
]

export const SUBSCRIPTION_STATUS_OPTIONS: Array<{
  value: FinanceSubscriptionStatus
  label: string
}> = [
  { value: 'active', label: 'Ativa' },
  { value: 'past_due', label: 'Em atraso' },
  { value: 'suspended', label: 'Suspensa' },
  { value: 'cancelled', label: 'Cancelada' },
]

export const BILLING_STATUS_OPTIONS: Array<{ value: FinanceBillingStatus; label: string }> = [
  { value: 'pending', label: 'Pendente' },
  { value: 'awaiting_payment', label: 'Aguardando pagamento' },
  { value: 'paid', label: 'Pago' },
  { value: 'overdue', label: 'Vencido' },
  { value: 'cancelled', label: 'Cancelado' },
  { value: 'refunded', label: 'Estornado' },
]

export const PAYMENT_METHOD_OPTIONS: Array<{ value: FinancePaymentMethod; label: string }> = [
  { value: 'pix', label: 'PIX' },
  { value: 'boleto', label: 'Boleto' },
  { value: 'credit_card', label: 'Cartão de crédito' },
]

export const REPORT_TYPE_OPTIONS: Array<{ value: FinanceReportType; label: string }> = [
  { value: 'billings', label: 'Cobranças' },
  { value: 'delinquency', label: 'Inadimplência' },
  { value: 'receipts', label: 'Recebimentos' },
  { value: 'subscriptions', label: 'Assinaturas' },
  { value: 'blocked_clients', label: 'Clientes bloqueados' },
]

export function periodicityLabel(value: BillingPeriodicity | string): string {
  return PERIODICITY_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function subscriptionStatusLabel(value: FinanceSubscriptionStatus | string): string {
  return SUBSCRIPTION_STATUS_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function billingStatusLabel(value: FinanceBillingStatus | string): string {
  return BILLING_STATUS_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function paymentMethodLabel(value: FinancePaymentMethod | string | null | undefined): string {
  if (!value) return '—'
  return PAYMENT_METHOD_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function subscriptionStatusBadgeVariant(
  status: FinanceSubscriptionStatus | string,
): 'success' | 'warning' | 'neutral' | 'danger' {
  switch (status) {
    case 'active':
      return 'success'
    case 'past_due':
      return 'danger'
    case 'suspended':
      return 'warning'
    case 'cancelled':
      return 'neutral'
    default:
      return 'neutral'
  }
}

export function billingStatusBadgeVariant(
  status: FinanceBillingStatus | string,
): 'primary' | 'warning' | 'success' | 'danger' | 'neutral' {
  switch (status) {
    case 'pending':
      return 'primary'
    case 'awaiting_payment':
      return 'warning'
    case 'paid':
      return 'success'
    case 'overdue':
      return 'danger'
    case 'cancelled':
    case 'refunded':
      return 'neutral'
    default:
      return 'neutral'
  }
}

/** Converte valor em reais (UI) para centavos (API). */
export function reaisToCents(value: number | string): number {
  const n = typeof value === 'string' ? Number(value.replace(',', '.')) : value
  if (!Number.isFinite(n)) return 0
  return Math.round(n * 100)
}

/** Converte centavos (API) para reais (UI). */
export function centsToReais(cents: number | null | undefined): number {
  if (cents == null) return 0
  return cents / 100
}
