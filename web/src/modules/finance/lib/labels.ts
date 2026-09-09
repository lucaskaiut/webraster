import type {
  BillingPeriodicity,
  FinanceContractStatus,
  FinancePaymentMethod,
  FinanceReceivableStatus,
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

export const CONTRACT_STATUS_OPTIONS: Array<{ value: FinanceContractStatus; label: string }> = [
  { value: 'active', label: 'Ativo' },
  { value: 'suspended', label: 'Suspenso' },
  { value: 'cancelled', label: 'Cancelado' },
]

export const SUBSCRIPTION_STATUS_OPTIONS: Array<{
  value: FinanceSubscriptionStatus
  label: string
}> = [
  { value: 'active', label: 'Ativa' },
  { value: 'suspended', label: 'Suspensa' },
  { value: 'cancelled', label: 'Cancelada' },
]

export const RECEIVABLE_STATUS_OPTIONS: Array<{ value: FinanceReceivableStatus; label: string }> = [
  { value: 'pending', label: 'Pendente' },
  { value: 'awaiting_payment', label: 'Aguardando pagamento' },
  { value: 'received', label: 'Recebido' },
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
  { value: 'receivables', label: 'Cobranças' },
  { value: 'delinquency', label: 'Inadimplência' },
  { value: 'receipts', label: 'Recebimentos' },
  { value: 'subscriptions', label: 'Assinaturas' },
  { value: 'blocked_clients', label: 'Clientes bloqueados' },
]

export const ASAAS_ENVIRONMENT_OPTIONS = [
  { value: 'sandbox', label: 'Sandbox' },
  { value: 'production', label: 'Produção' },
]

export function periodicityLabel(value: BillingPeriodicity | string): string {
  return PERIODICITY_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function contractStatusLabel(value: FinanceContractStatus | string): string {
  return CONTRACT_STATUS_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function subscriptionStatusLabel(value: FinanceSubscriptionStatus | string): string {
  return SUBSCRIPTION_STATUS_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function receivableStatusLabel(value: FinanceReceivableStatus | string): string {
  return RECEIVABLE_STATUS_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function paymentMethodLabel(value: FinancePaymentMethod | string | null | undefined): string {
  if (!value) return '—'
  return PAYMENT_METHOD_OPTIONS.find((item) => item.value === value)?.label ?? value
}

export function contractStatusBadgeVariant(
  status: FinanceContractStatus | string,
): 'success' | 'warning' | 'neutral' | 'danger' {
  switch (status) {
    case 'active':
      return 'success'
    case 'suspended':
      return 'warning'
    case 'cancelled':
      return 'neutral'
    default:
      return 'neutral'
  }
}

export function subscriptionStatusBadgeVariant(
  status: FinanceSubscriptionStatus | string,
): 'success' | 'warning' | 'neutral' {
  switch (status) {
    case 'active':
      return 'success'
    case 'suspended':
      return 'warning'
    case 'cancelled':
      return 'neutral'
    default:
      return 'neutral'
  }
}

export function receivableStatusBadgeVariant(
  status: FinanceReceivableStatus | string,
): 'primary' | 'warning' | 'success' | 'danger' | 'neutral' {
  switch (status) {
    case 'pending':
      return 'primary'
    case 'awaiting_payment':
      return 'warning'
    case 'received':
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
