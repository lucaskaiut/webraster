import { z } from 'zod'

export const serviceSchema = z
  .object({
    name: z.string().min(1, 'Informe o nome'),
    amount: z.coerce.number().min(0, 'Informe um valor válido'),
    estimated_minutes: z.coerce
      .number()
      .int('Informe minutos inteiros')
      .min(1, 'Informe ao menos 1 minuto')
      .max(1440, 'Máximo de 24 horas'),
    commission_type: z.enum(['', 'percentage', 'fixed']),
    commission_value: z.coerce.number().min(0, 'Informe um valor válido'),
    linked_service_ids: z.array(z.string()),
  })
  .superRefine((values, ctx) => {
    if (values.commission_type === 'percentage' && values.commission_value > 100) {
      ctx.addIssue({
        code: 'custom',
        path: ['commission_value'],
        message: 'O percentual não pode ser superior a 100%',
      })
    }
  })

export type ServiceFormValues = z.infer<typeof serviceSchema>
