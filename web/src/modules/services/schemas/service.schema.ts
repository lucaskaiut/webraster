import { z } from 'zod'

export const serviceSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  amount: z.coerce.number().min(0, 'Informe um valor válido'),
  estimated_minutes: z.coerce
    .number()
    .int('Informe minutos inteiros')
    .min(1, 'Informe ao menos 1 minuto')
    .max(1440, 'Máximo de 24 horas'),
  linked_service_ids: z.array(z.string()),
})

export type ServiceFormValues = z.infer<typeof serviceSchema>
