import { z } from 'zod'
import { isValidCpf, isValidCpfOrCnpj } from '@/shared/utils/document'

export const clientSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  document: z
    .string()
    .min(1, 'Informe o CPF ou CNPJ')
    .refine(isValidCpfOrCnpj, 'Informe um CPF ou CNPJ válido'),
  email: z
    .string()
    .refine((value) => !value || z.string().email().safeParse(value).success, 'Informe um e-mail válido'),
  phone: z.string(),
  street: z.string(),
  number: z.string(),
  complement: z.string(),
  neighborhood: z.string(),
  city: z.string(),
  state: z
    .string()
    .refine((value) => !value || value.length === 2, 'Informe a UF com 2 letras'),
  zip: z.string(),
  is_active: z.boolean(),
})

export type ClientFormValues = z.infer<typeof clientSchema>

export const createClientUserSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  email: z.string().min(1, 'Informe o e-mail').email('Informe um e-mail válido'),
  phone: z.string(),
  document: z.string().refine((value) => !value || isValidCpf(value), 'Informe um CPF válido'),
  password: z.string().min(8, 'A senha deve ter no mínimo 8 caracteres'),
  role_ids: z.array(z.number()),
})

export type ClientUserFormValues = z.infer<typeof createClientUserSchema>
