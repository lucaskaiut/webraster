import { z } from 'zod'

export const serviceOrderSchema = z.object({
  address: z
    .string()
    .trim()
    .min(1, 'Informe o endereço do atendimento')
    .max(500, 'Máximo de 500 caracteres'),
  service_id: z.string().min(1, 'Selecione o serviço'),
  technician_id: z.string().optional().or(z.literal('')),
  scheduled_start_at: z.string().min(1, 'Selecione um horário'),
  scheduled_end_at: z.string().optional().or(z.literal('')),
})

export type ServiceOrderFormValues = z.infer<typeof serviceOrderSchema>
