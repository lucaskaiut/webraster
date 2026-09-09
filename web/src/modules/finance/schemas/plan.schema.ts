import { z } from 'zod'

export const financePlanSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  description: z.string(),
  amount: z.coerce.number().min(0, 'Informe um valor válido'),
  periodicity: z.enum(['monthly', 'bimonthly', 'quarterly', 'semiannual', 'annual']),
  device_limit: z.string(),
  is_active: z.boolean(),
})

export type FinancePlanFormValues = z.infer<typeof financePlanSchema>
