import { z } from 'zod'
import { isValidCpf } from '@/shared/utils/document'

const baseUserSchema = {
  name: z.string().min(1, 'Informe o nome'),
  email: z.string().min(1, 'Informe o e-mail').email('Informe um e-mail válido'),
  phone: z.string(),
  document: z.string().refine((value) => !value || isValidCpf(value), 'Informe um CPF válido'),
  role_ids: z.array(z.number()).min(1, 'Selecione ao menos um perfil'),
}

export const createUserSchema = z
  .object({
    ...baseUserSchema,
    password: z.string().min(8, 'A senha deve ter no mínimo 8 caracteres'),
    password_confirmation: z.string().min(1, 'Confirme a senha'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'As senhas não coincidem',
  })

export const updateUserSchema = z
  .object({
    ...baseUserSchema,
    password: z
      .string()
      .refine((value) => !value || value.length >= 8, 'A senha deve ter no mínimo 8 caracteres'),
    password_confirmation: z.string(),
  })
  .refine((data) => !data.password || data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'As senhas não coincidem',
  })

export type UserFormValues = z.infer<typeof createUserSchema>
