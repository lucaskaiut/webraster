import { z } from 'zod'

export const planSchema = z.object({
  name: z.string().min(1, 'Informe o nome do plano').max(255),
  description: z.string().max(2000).optional().or(z.literal('')),
  price: z.coerce.number().min(0.01, 'Informe um valor válido'),
  recurrence_value: z.coerce.number().int().min(1, 'Informe a recorrência'),
  recurrence_unit: z.enum(['days', 'weeks', 'months', 'years']),
  free_trial_days: z.coerce.number().int().min(0).max(365).default(0),
  active: z.boolean().default(true),
})

export type PlanFormValues = z.infer<typeof planSchema>

export const RECURRENCE_UNITS = [
  { value: 'days', label: 'Dias' },
  { value: 'weeks', label: 'Semanas' },
  { value: 'months', label: 'Meses' },
  { value: 'years', label: 'Anos' },
] as const
