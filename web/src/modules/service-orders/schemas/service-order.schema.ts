import { z } from 'zod'
import { parseLocalDateTime } from '@/shared/utils/date'

export const serviceOrderSchema = z
  .object({
    type: z.enum(['installation', 'maintenance', 'removal']),
    priority: z.enum(['low', 'normal', 'high', 'urgent']),
    client_id: z.string().min(1, 'Selecione o cliente'),
    vehicle_id: z.string().optional().or(z.literal('')),
    equipment_id: z.string().optional().or(z.literal('')),
    technician_id: z.string().optional().or(z.literal('')),
    scheduled_start_at: z.string().optional().or(z.literal('')),
    scheduled_end_at: z.string().optional().or(z.literal('')),
    description: z.string().max(5000).optional().or(z.literal('')),
    notes: z.string().max(5000).optional().or(z.literal('')),
    ignore_schedule_conflict: z.boolean().optional(),
  })
  .superRefine((values, ctx) => {
    const start = values.scheduled_start_at ? parseLocalDateTime(values.scheduled_start_at) : null
    const end = values.scheduled_end_at ? parseLocalDateTime(values.scheduled_end_at) : null

    if (start && end && end <= start) {
      ctx.addIssue({
        code: 'custom',
        path: ['scheduled_end_at'],
        message: 'O horário final deve ser posterior ao inicial.',
      })
    }
  })

export type ServiceOrderFormValues = z.infer<typeof serviceOrderSchema>
