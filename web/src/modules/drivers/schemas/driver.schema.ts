import { z } from 'zod'
import { isValidCpf } from '@/shared/utils/document'

export const driverSchema = z.object({
  client_id: z.string().min(1, 'Selecione o cliente'),
  name: z.string().min(1, 'Informe o nome'),
  document: z.string().refine((value) => !value || isValidCpf(value), 'Informe um CPF válido'),
  phone: z.string(),
  email: z
    .string()
    .refine((value) => !value || z.string().email().safeParse(value).success, 'Informe um e-mail válido'),
  cnh_number: z.string(),
  cnh_expires_at: z.string(),
  notes: z.string(),
  is_active: z.boolean(),
})

export type DriverFormValues = z.infer<typeof driverSchema>
