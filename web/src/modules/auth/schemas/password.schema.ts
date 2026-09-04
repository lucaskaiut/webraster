import { z } from 'zod'

export const forgotPasswordSchema = z.object({
  email: z.string().min(1, 'Informe seu e-mail').email('Informe um e-mail válido'),
})

export type ForgotPasswordFormValues = z.infer<typeof forgotPasswordSchema>

export const resetPasswordSchema = z
  .object({
    email: z.string().min(1, 'Informe seu e-mail').email('Informe um e-mail válido'),
    token: z.string().min(1, 'Token inválido'),
    password: z.string().min(8, 'A senha deve ter no mínimo 8 caracteres'),
    password_confirmation: z.string().min(1, 'Confirme sua senha'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'As senhas não coincidem',
  })

export type ResetPasswordFormValues = z.infer<typeof resetPasswordSchema>
