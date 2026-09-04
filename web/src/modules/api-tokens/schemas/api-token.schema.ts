import { z } from 'zod'
import { Permission } from '@/shared/constants/permissions'

export const EXPIRATION_OPTIONS = [
  { value: 'never', label: 'Nunca expira', description: 'O token permanece válido até ser revogado.' },
  { value: '30', label: '30 dias', description: 'Expira automaticamente em 30 dias.' },
  { value: '90', label: '90 dias', description: 'Expira automaticamente em 90 dias.' },
] as const

const permissionValues = Object.values(Permission) as [Permission, ...Permission[]]

export const apiTokenSchema = z.object({
  name: z.string().min(1, 'Informe um nome para identificar o token'),
  expiration: z.enum(['never', '30', '90']),
  permissions: z.array(z.enum(permissionValues)),
})

export type ApiTokenFormValues = z.infer<typeof apiTokenSchema>

export function resolveExpiresAt(expiration: ApiTokenFormValues['expiration']): string | null {
  if (expiration === 'never') return null

  const date = new Date()
  date.setDate(date.getDate() + Number(expiration))

  return date.toISOString()
}
