import { z } from 'zod'

export const ALERT_CONFIG_TYPES = [
  'speed',
  'ignition_on',
  'ignition_off',
  'sos',
  'offline',
  'battery',
  'jamming',
  'device_alarm',
] as const

export type AlertConfigType = (typeof ALERT_CONFIG_TYPES)[number]

export const alertConfigSchema = z
  .object({
    name: z.string().max(150).optional().or(z.literal('')),
    type: z.enum(ALERT_CONFIG_TYPES),
    client_id: z.string().optional().or(z.literal('')),
    vehicle_id: z.string().optional().or(z.literal('')),
    is_enabled: z.boolean(),
    notify_in_app: z.boolean(),
    notify_email: z.boolean(),
    notify_push: z.boolean(),
    speed_limit_kmh: z.coerce.number().min(1).max(300).optional(),
    min_duration_seconds: z.coerce.number().min(0).max(3600).optional(),
    offline_minutes: z.coerce.number().min(1).max(1440).optional(),
    battery_threshold: z.coerce.number().min(1).max(100).optional(),
  })
  .superRefine((values, ctx) => {
    if (values.client_id && values.vehicle_id) {
      ctx.addIssue({
        code: 'custom',
        path: ['vehicle_id'],
        message: 'Informe apenas veículo ou cliente, não ambos.',
      })
    }
  })

export type AlertConfigFormValues = z.infer<typeof alertConfigSchema>
