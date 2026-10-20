import { z } from 'zod'

export const stockSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'Informe o nome do estoque')
    .max(120, 'Use no máximo 120 caracteres'),
  description: z.string().max(2000, 'Use no máximo 2000 caracteres'),
  is_active: z.boolean(),
})

export type StockFormValues = z.infer<typeof stockSchema>
