import type { ServiceOrderPriority, ServiceOrderStatus } from '@/shared/types/models'

export const SERVICE_ORDER_STATUS_OPTIONS: Array<{ value: ServiceOrderStatus; label: string }> = [
  { value: 'open', label: 'Aberta' },
  { value: 'in_progress', label: 'Em andamento' },
  { value: 'completed', label: 'Concluída' },
  { value: 'cancelled', label: 'Cancelada' },
]

export const SERVICE_ORDER_PRIORITY_OPTIONS: Array<{ value: ServiceOrderPriority; label: string }> = [
  { value: 'low', label: 'Baixa' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'Alta' },
  { value: 'urgent', label: 'Urgente' },
]

export const KANBAN_COLUMNS: ServiceOrderStatus[] = ['open', 'in_progress', 'completed', 'cancelled']

/** Situações possíveis ao criar uma OS (antes dela entrar no fluxo). */
export const SERVICE_ORDER_INITIAL_STATUSES: ServiceOrderStatus[] = ['open', 'in_progress']

/**
 * Situações disponíveis no formulário de edição: a atual e as transições
 * permitidas pelo fluxo (mesmas regras validadas no backend).
 */
export const SERVICE_ORDER_STATUS_TRANSITIONS: Record<ServiceOrderStatus, ServiceOrderStatus[]> = {
  open: ['open', 'in_progress', 'cancelled'],
  in_progress: ['in_progress', 'completed', 'cancelled'],
  completed: ['completed'],
  cancelled: ['cancelled'],
}

export function serviceOrderStatusOptions(statuses: ServiceOrderStatus[]) {
  return statuses.map((value) => ({ value, label: statusLabel(value) }))
}

const HISTORY_ACTION_LABELS: Record<string, string> = {
  created: 'Criada',
  updated: 'Atualizada',
  status_changed: 'Situação alterada',
  technician_changed: 'Técnico alterado',
  schedule_changed: 'Agendamento alterado',
  services_changed: 'Serviços alterados',
  completed: 'Concluída',
  cancelled: 'Cancelada',
  billing_generated: 'Cobrança gerada',
}

const HISTORY_FIELD_LABELS: Record<string, string> = {
  status: 'Situação',
  items: 'Serviços',
  billing: 'Cobrança',
  priority: 'Prioridade',
  client_id: 'Cliente',
  vehicle_id: 'Veículo',
  equipment_id: 'Dispositivo',
  technician_id: 'Técnico',
  scheduled_start_at: 'Início',
  scheduled_end_at: 'Fim',
  description: 'Descrição',
  notes: 'Observações',
  execution_notes: 'Observações da execução',
}

export function historyActionLabel(action: string): string {
  return HISTORY_ACTION_LABELS[action] ?? action
}

export function historyFieldLabel(field: string): string {
  return HISTORY_FIELD_LABELS[field] ?? field
}

/** Converte valores conhecidos (status, prioridade) para o rótulo em português. */
export function historyValueLabel(value: string | null): string {
  if (!value || value.trim() === '') return '—'

  const trimmed = value.trim()

  return (
    SERVICE_ORDER_STATUS_OPTIONS.find((item) => item.value === trimmed)?.label ??
    SERVICE_ORDER_PRIORITY_OPTIONS.find((item) => item.value === trimmed)?.label ??
    value
  )
}

/** Valores estruturados (JSON de serviços, etc.) não são exibidos como "antes → depois". */
export function isReadableHistoryValue(value: string | null): boolean {
  if (!value) return true

  const trimmed = value.trim()

  return !trimmed.startsWith('[') && !trimmed.startsWith('{')
}

export function statusLabel(status: ServiceOrderStatus): string {
  return SERVICE_ORDER_STATUS_OPTIONS.find((item) => item.value === status)?.label ?? status
}

export function priorityLabel(priority: ServiceOrderPriority): string {
  return SERVICE_ORDER_PRIORITY_OPTIONS.find((item) => item.value === priority)?.label ?? priority
}

export function priorityBadgeVariant(
  priority: ServiceOrderPriority,
): 'neutral' | 'primary' | 'warning' | 'danger' {
  switch (priority) {
    case 'low':
      return 'neutral'
    case 'normal':
      return 'primary'
    case 'high':
      return 'warning'
    case 'urgent':
      return 'danger'
  }
}

export function statusBadgeVariant(
  status: ServiceOrderStatus,
): 'neutral' | 'primary' | 'success' | 'warning' | 'danger' {
  switch (status) {
    case 'open':
      return 'primary'
    case 'in_progress':
      return 'warning'
    case 'completed':
      return 'success'
    case 'cancelled':
      return 'neutral'
  }
}
