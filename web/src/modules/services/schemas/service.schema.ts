import { z } from 'zod'

export const serviceSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  amount: z.coerce.number().min(0, 'Informe um valor válido'),
})

export type ServiceFormValues = z.infer<typeof serviceSchema>
