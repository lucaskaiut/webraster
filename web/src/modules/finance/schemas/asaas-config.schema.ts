import { z } from 'zod'

export const asaasConfigSchema = z.object({
  environment: z.enum(['sandbox', 'production']),
  api_key: z.string(),
  webhook_token: z.string(),
  is_active: z.boolean(),
})

export type AsaasConfigFormValues = z.infer<typeof asaasConfigSchema>
