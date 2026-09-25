import { z } from 'zod'
import { isValidCpfOrCnpj } from '@/shared/utils/document'

const tenantFields = z.object({
  name: z.string().min(1, 'Informe o nome'),
  document: z.string().refine((value) => isValidCpfOrCnpj(value), 'Informe um CPF ou CNPJ válido'),
  email: z.string().min(1, 'Informe o e-mail').email('Informe um e-mail válido'),
  phone: z.string().min(1, 'Informe o telefone'),
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

export const tenantSettingsSchema = tenantFields.extend({
  logo_path: z.string().nullable(),
  favicon_path: z.string().nullable(),
})

/** @deprecated use createChildTenantSchema */
export const childTenantSchema = createChildTenantSchema

export type CreateChildTenantFormValues = z.infer<typeof createChildTenantSchema>
export type UpdateChildTenantFormValues = z.infer<typeof updateChildTenantSchema>
export type ChildTenantFormValues = CreateChildTenantFormValues
export type TenantSettingsFormValues = z.infer<typeof tenantSettingsSchema>
