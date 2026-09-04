import { z } from 'zod'
import { isValidCpf, isValidCpfOrCnpj } from '@/shared/utils/document'

export const loginSchema = z.object({
  email: z.string().min(1, 'Informe seu e-mail').email('Informe um e-mail válido'),
  password: z.string().min(1, 'Informe sua senha'),
})

export type LoginFormValues = z.infer<typeof loginSchema>

const DOMAIN_REGEX = /^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i

export const registerSchema = z.object({
  tenant: z.object({
    name: z.string().min(1, 'Informe o nome da empresa'),
    document: z
      .string()
      .min(1, 'Informe o CPF ou CNPJ')
      .refine(isValidCpfOrCnpj, 'Informe um CPF ou CNPJ válido'),
    email: z.string().min(1, 'Informe o e-mail da empresa').email('Informe um e-mail válido'),
    phone: z.string().min(10, 'Informe um telefone válido'),
    domain: z
      .string()
      .min(1, 'Informe o domínio')
      .regex(DOMAIN_REGEX, 'Informe um domínio válido (ex.: empresa.com.br)'),
  }),
  user: z
    .object({
      name: z.string().min(1, 'Informe seu nome'),
      email: z.string().min(1, 'Informe seu e-mail').email('Informe um e-mail válido'),
      phone: z.string().min(10, 'Informe um telefone válido'),
      document: z.string().min(1, 'Informe seu CPF').refine(isValidCpf, 'Informe um CPF válido'),
      password: z.string().min(8, 'A senha deve ter no mínimo 8 caracteres'),
      password_confirmation: z.string().min(1, 'Confirme sua senha'),
    })
    .refine((data) => data.password === data.password_confirmation, {
      path: ['password_confirmation'],
      message: 'As senhas não coincidem',
    }),
})

export type RegisterFormValues = z.infer<typeof registerSchema>
