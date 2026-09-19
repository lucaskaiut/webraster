import type { ContractSignatureStatus } from '@/shared/types/models'

export const CONTRACT_SIGNATURE_STATUS_OPTIONS: Array<{
  value: ContractSignatureStatus
  label: string
}> = [
  { value: 'pending', label: 'Pendente' },
  { value: 'signed', label: 'Assinado' },
]

export function signatureStatusLabel(status: ContractSignatureStatus): string {
  return CONTRACT_SIGNATURE_STATUS_OPTIONS.find((item) => item.value === status)?.label ?? status
}

export function signatureStatusBadgeVariant(
  status: ContractSignatureStatus,
): 'neutral' | 'success' | 'warning' {
  switch (status) {
    case 'signed':
      return 'success'
    case 'pending':
      return 'warning'
  }
}
