import type { CommissionStatus } from '@/shared/types/models'

export const COMMISSION_STATUS_OPTIONS: Array<{ value: CommissionStatus; label: string }> = [
  { value: 'forecast', label: 'Previstas' },
  { value: 'finalized', label: 'Finalizadas' },
]

export function commissionStatusLabel(status: CommissionStatus | string): string {
  return COMMISSION_STATUS_OPTIONS.find((item) => item.value === status)?.label ?? status
}

export function commissionStatusBadgeVariant(
  status: CommissionStatus | string,
): 'success' | 'warning' | 'neutral' {
  switch (status) {
    case 'finalized':
      return 'success'
    case 'forecast':
      return 'warning'
    default:
      return 'neutral'
  }
}
