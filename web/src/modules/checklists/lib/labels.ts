import type {
  ChecklistAnswerType,
  ChecklistPhotoRule,
  ChecklistType,
  ServiceOrderChecklistStatus,
} from '@/shared/types/models'

export const CHECKLIST_TYPE_OPTIONS: Array<{ value: ChecklistType; label: string }> = [
  { value: 'initial', label: 'Inicial' },
  { value: 'final', label: 'Final' },
]

export const CHECKLIST_ANSWER_TYPE_OPTIONS: Array<{
  value: ChecklistAnswerType
  label: string
}> = [
  { value: 'text', label: 'Texto' },
  { value: 'single_choice', label: 'Seleção única' },
  { value: 'multiple_choice', label: 'Múltipla seleção' },
]

export const CHECKLIST_PHOTO_RULE_OPTIONS: Array<{
  value: ChecklistPhotoRule
  label: string
}> = [
  { value: 'required', label: 'Obrigatória' },
  { value: 'optional', label: 'Opcional' },
  { value: 'none', label: 'Não se aplica' },
]

export function checklistTypeLabel(type: ChecklistType | string): string {
  return CHECKLIST_TYPE_OPTIONS.find((item) => item.value === type)?.label ?? type
}

export function checklistTypeBadgeVariant(
  type: ChecklistType | string,
): 'primary' | 'warning' | 'neutral' {
  switch (type) {
    case 'initial':
      return 'primary'
    case 'final':
      return 'warning'
    default:
      return 'neutral'
  }
}

export function checklistStatusLabel(status: ServiceOrderChecklistStatus | string): string {
  return status === 'completed' ? 'Concluído' : 'Pendente'
}
