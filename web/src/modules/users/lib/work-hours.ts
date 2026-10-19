import type { UserWorkHour } from '@/shared/types/models'

/** Ordem de exibição: segunda a domingo (weekday 0 = domingo, como no backend). */
export const WEEKDAYS = [
  { value: 1, label: 'Segunda', short: 'Seg' },
  { value: 2, label: 'Terça', short: 'Ter' },
  { value: 3, label: 'Quarta', short: 'Qua' },
  { value: 4, label: 'Quinta', short: 'Qui' },
  { value: 5, label: 'Sexta', short: 'Sex' },
  { value: 6, label: 'Sábado', short: 'Sáb' },
  { value: 0, label: 'Domingo', short: 'Dom' },
] as const

export const DEFAULT_WORK_HOURS: UserWorkHour[] = [1, 2, 3, 4, 5].flatMap((weekday) => [
  { weekday, starts_at: '08:00', ends_at: '12:00' },
  { weekday, starts_at: '13:00', ends_at: '18:00' },
])
