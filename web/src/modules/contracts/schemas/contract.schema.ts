import { z } from 'zod'
import { isEmptyRichText } from '../lib/variables'

export const contractSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  body: z
    .string()
    .refine((value) => !isEmptyRichText(value), 'Informe o conteúdo do contrato'),
})

export type ContractFormValues = z.infer<typeof contractSchema>
