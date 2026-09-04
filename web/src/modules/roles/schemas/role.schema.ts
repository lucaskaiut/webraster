import { z } from 'zod'
import { Permission } from '@/shared/constants/permissions'

const permissionValues = Object.values(Permission) as [Permission, ...Permission[]]

export const roleSchema = z.object({
  name: z.string().min(1, 'Informe o nome do perfil'),
  description: z.string(),
  permissions: z.array(z.enum(permissionValues)),
})

export type RoleFormValues = z.infer<typeof roleSchema>
