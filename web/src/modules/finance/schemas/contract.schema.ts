import { z } from 'zod'

export const financeContractSchema = z.object({
  client_id: z.string().min(1, 'Selecione o cliente'),
  plan_id: z.string(),
  starts_at: z.string().min(1, 'Informe a data de início'),
  ends_at: z.string(),
  periodicity: z.enum(['monthly', 'bimonthly', 'quarterly', 'semiannual', 'annual']),
  due_day: z.coerce.number().int().min(1, 'Mínimo 1').max(28, 'Máximo 28'),
  amount: z.coerce.number().min(0, 'Informe um valor válido'),
  discount: z.coerce.number().min(0, 'Informe um desconto válido'),
  fine_percent: z.coerce.number().min(0).max(100),
  interest_percent: z.coerce.number().min(0).max(100),
  device_quantity: z.coerce.number().int().min(0),
  auto_renew: z.boolean(),
  block_on_overdue: z.boolean(),
  block_after_days: z.coerce.number().int().min(0),
  notes: z.string(),
})

export type FinanceContractFormValues = z.infer<typeof financeContractSchema>
