import { z } from 'zod'
import { isValidCpfOrCnpj } from '@/shared/utils/document'

const tenantFields = z.object({
  name: z.string().min(1, 'Informe o nome'),
  document: z.string().refine((value) => isValidCpfOrCnpj(value), 'Informe um CPF ou CNPJ válido'),
  email: z.string().min(1, 'Informe o e-mail').email('Informe um e-mail válido'),
  phone: z.string().min(1, 'Informe o telefone'),
  domain: z
    .string()
    .min(1, 'Informe o domínio')
    .regex(
      /^(?=.{1,253}$)((?!-)[a-z0-9-]{1,63}(?<!-)\.)+[a-z]{2,63}$/,
      'Informe um domínio válido (ex.: empresa.com.br)',
    ),
})

export const createChildTenantSchema = z.object({
  tenant: tenantFields,
  user: z.object({
    name: z.string().min(1, 'Informe o nome'),
    email: z.string().min(1, 'Informe o e-mail').email('Informe um e-mail válido'),
    password: z.string().min(8, 'A senha deve ter no mínimo 8 caracteres'),
  }),
})

export const updateChildTenantSchema = z.object({
  tenant: tenantFields,
})

/** @deprecated use createChildTenantSchema */
export const childTenantSchema = createChildTenantSchema

export type CreateChildTenantFormValues = z.infer<typeof createChildTenantSchema>
export type UpdateChildTenantFormValues = z.infer<typeof updateChildTenantSchema>
export type ChildTenantFormValues = CreateChildTenantFormValues
