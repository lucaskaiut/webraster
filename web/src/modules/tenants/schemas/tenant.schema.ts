import { z } from 'zod'
import { isValidCpfOrCnpj } from '@/shared/utils/document'
import { vehicleAlertConfigSchema } from '@/modules/vehicles/schemas/vehicle.schema'

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

const hexColor = z
  .string()
  .regex(/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/, 'Informe uma cor hexadecimal válida')

export const tenantSettingsSchema = tenantFields.extend({
  identifier: z
    .string()
    .min(3, 'O identificador deve ter no mínimo 3 caracteres')
    .max(60, 'O identificador deve ter no máximo 60 caracteres')
    .regex(/^[A-Za-z0-9_-]+$/, 'Use apenas letras, números, hífen e underline'),
  logo_path: z.string().nullable(),
  favicon_path: z.string().nullable(),
  signature_path: z.string().nullable(),
})

export const tenantAppSettingsSchema = z.object({
  app_name: z.string().max(255, 'O nome deve ter no máximo 255 caracteres').nullable(),
  app_icon_path: z.string().nullable(),
  app_logo_path: z.string().nullable(),
  app_primary_color: hexColor.nullable(),
  app_secondary_color: hexColor.nullable(),
})

export const tenantAlertSettingsSchema = z.object({
  vehicle_alert_defaults: z.array(vehicleAlertConfigSchema),
})

/** @deprecated use createChildTenantSchema */
export const childTenantSchema = createChildTenantSchema

export type CreateChildTenantFormValues = z.infer<typeof createChildTenantSchema>
export type UpdateChildTenantFormValues = z.infer<typeof updateChildTenantSchema>
export type ChildTenantFormValues = CreateChildTenantFormValues
export type TenantSettingsFormValues = z.infer<typeof tenantSettingsSchema>
export type TenantAppSettingsFormValues = z.infer<typeof tenantAppSettingsSchema>
export type TenantAlertSettingsFormValues = z.infer<typeof tenantAlertSettingsSchema>
