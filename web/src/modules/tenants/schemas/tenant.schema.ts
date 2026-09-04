import { z } from 'zod'
import { isValidCpfOrCnpj } from '@/shared/utils/document'

export const childTenantSchema = z.object({
  tenant: z.object({
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
  }),
  user: z.object({
    name: z.string().min(1, 'Informe o nome'),
    email: z.string().min(1, 'Informe o e-mail').email('Informe um e-mail válido'),
    password: z.string().min(8, 'A senha deve ter no mínimo 8 caracteres'),
  }),
  plan_id: z.string().nullable(),
})

export type ChildTenantFormValues = z.infer<typeof childTenantSchema>
