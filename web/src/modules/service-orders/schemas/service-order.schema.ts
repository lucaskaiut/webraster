import { z } from 'zod'
import { parseLocalDateTime } from '@/shared/utils/date'

export const serviceOrderSchema = z
  .object({
    priority: z.enum(['low', 'normal', 'high', 'urgent']),
    status: z.enum(['open', 'in_progress', 'completed', 'cancelled']),
    client_id: z.string().min(1, 'Selecione o cliente'),
    vehicle_id: z.string().optional().or(z.literal('')),
    equipment_id: z.string().optional().or(z.literal('')),
    technician_id: z.string().optional().or(z.literal('')),
    scheduled_start_at: z.string().optional().or(z.literal('')),
    scheduled_end_at: z.string().optional().or(z.literal('')),
    address: z.string().max(500, 'Máximo de 500 caracteres').optional().or(z.literal('')),
    estimated_duration_minutes: z
      .string()
      .regex(/^\d*$/, 'Informe um número inteiro de minutos')
      .refine((value) => !value || (Number(value) >= 1 && Number(value) <= 1440), 'Entre 1 e 1440 minutos')
      .optional()
      .or(z.literal('')),
    description: z.string().max(5000).optional().or(z.literal('')),
    notes: z.string().max(5000).optional().or(z.literal('')),
    cancellation_reason: z.string().max(2000).optional().or(z.literal('')),
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

    if (values.status === 'cancelled' && !values.cancellation_reason?.trim()) {
      ctx.addIssue({
        code: 'custom',
        path: ['cancellation_reason'],
        message: 'Informe o motivo do cancelamento.',
      })
    }
  })

export type ServiceOrderFormValues = z.infer<typeof serviceOrderSchema>
