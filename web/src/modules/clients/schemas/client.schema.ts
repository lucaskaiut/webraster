import { z } from 'zod'
import { isValidCpfOrCnpj } from '@/shared/utils/document'

export const clientSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  legal_name: z.string(),
  trade_name: z.string(),
  document: z
    .string()
    .min(1, 'Informe o CPF ou CNPJ')
    .refine(isValidCpfOrCnpj, 'Informe um CPF ou CNPJ válido'),
  state_registration: z.string(),
  email: z
    .string()
    .refine((value) => !value || z.string().email().safeParse(value).success, 'Informe um e-mail válido'),
  financial_email: z
    .string()
    .refine(
      (value) => !value || z.string().email().safeParse(value).success,
      'Informe um e-mail financeiro válido',
    ),
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

export const clientBasicSchema = clientSchema.pick({
  name: true,
  legal_name: true,
  trade_name: true,
  document: true,
  state_registration: true,
  email: true,
  financial_email: true,
  phone: true,
  is_active: true,
})

export type ClientBasicFormValues = z.infer<typeof clientBasicSchema>

export const clientAddressSchema = clientSchema.pick({
  street: true,
  number: true,
  complement: true,
  neighborhood: true,
  city: true,
  state: true,
  zip: true,
})

export type ClientAddressFormValues = z.infer<typeof clientAddressSchema>

export const createClientUserSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  email: z.string().min(1, 'Informe o e-mail').email('Informe um e-mail válido'),
  password: z
    .string()
    .refine((value) => !value || value.length >= 8, 'A senha deve ter no mínimo 8 caracteres'),
  role_ids: z.array(z.number()),
})

export type ClientUserFormValues = z.infer<typeof createClientUserSchema>

export const clientOrderSchema = z.object({
  due_day: z.coerce.number().int().min(1, 'Mínimo 1').max(28, 'Máximo 28'),
  periodicity: z.enum(['monthly', 'bimonthly', 'quarterly', 'semiannual', 'annual']),
  items: z
    .array(
      z.object({
        service_id: z.string().min(1, 'Selecione o serviço'),
        vehicle_ids: z.array(z.string()).min(1, 'Selecione ao menos um veículo'),
      }),
    )
    .min(1, 'Inclua ao menos um serviço'),
})

export type ClientOrderFormValues = z.infer<typeof clientOrderSchema>
