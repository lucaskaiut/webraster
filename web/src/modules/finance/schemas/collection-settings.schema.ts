import { z } from 'zod'

const days = (label: string) =>
  z
    .string()
    .regex(/^\d+$/, `Informe ${label} em dias`)
    .refine((value) => Number(value) >= 1 && Number(value) <= 30, 'Entre 1 e 30 dias')

export const collectionSettingsSchema = z.object({
  is_active: z.boolean(),
  before_due_enabled: z.boolean(),
  before_due_days: days('a antecedência'),
  before_due_message: z.string().max(2000, 'Máximo de 2000 caracteres'),
  due_date_enabled: z.boolean(),
  due_date_message: z.string().max(2000, 'Máximo de 2000 caracteres'),
  overdue_enabled: z.boolean(),
  overdue_interval_days: days('o intervalo'),
  overdue_max_reminders: z
    .string()
    .regex(/^\d*$/, 'Informe um número inteiro')
    .refine((value) => value === '' || Number(value) <= 100, 'Máximo de 100 avisos'),
  overdue_message: z.string().max(2000, 'Máximo de 2000 caracteres'),
  block_access_enabled: z.boolean(),
  block_access_after_days: z
    .string()
    .regex(/^\d+$/, 'Informe a quantidade de dias')
    .refine((value) => Number(value) >= 1 && Number(value) <= 90, 'Entre 1 e 90 dias'),
})

export type CollectionSettingsFormValues = z.infer<typeof collectionSettingsSchema>
