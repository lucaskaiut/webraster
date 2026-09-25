import { z } from 'zod'

export const NOTIFICATION_AUDIENCES = ['tenant', 'client', 'user'] as const

export type NotificationAudience = (typeof NOTIFICATION_AUDIENCES)[number]

export const notificationSchema = z
  .object({
    title: z.string().trim().min(1, 'Informe o título').max(120, 'Máximo de 120 caracteres'),
    body: z.string().trim().min(1, 'Informe a mensagem').max(1000, 'Máximo de 1000 caracteres'),
    audience: z.enum(NOTIFICATION_AUDIENCES),
    client_id: z.string().optional().or(z.literal('')),
    user_id: z.string().optional().or(z.literal('')),
    push: z.boolean(),
  })
  .superRefine((values, ctx) => {
    if (values.audience === 'client' && !values.client_id) {
      ctx.addIssue({ code: 'custom', path: ['client_id'], message: 'Selecione o cliente' })
    }

    if (values.audience === 'user' && !values.user_id) {
      ctx.addIssue({ code: 'custom', path: ['user_id'], message: 'Selecione o usuário' })
    }
  })

export type NotificationFormValues = z.infer<typeof notificationSchema>
