import type { ServiceOrderPriority, ServiceOrderStatus, ServiceOrderType } from '@/shared/types/models'

export const SERVICE_ORDER_TYPE_OPTIONS: Array<{ value: ServiceOrderType; label: string }> = [
  { value: 'installation', label: 'Instalação' },
  { value: 'maintenance', label: 'Manutenção' },
  { value: 'removal', label: 'Retirada' },
]

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

export function statusLabel(status: ServiceOrderStatus): string {
  return SERVICE_ORDER_STATUS_OPTIONS.find((item) => item.value === status)?.label ?? status
}

export function typeLabel(type: ServiceOrderType): string {
  return SERVICE_ORDER_TYPE_OPTIONS.find((item) => item.value === type)?.label ?? type
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
