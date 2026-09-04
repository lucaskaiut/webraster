import { z } from 'zod'
import { isValidCpfOrCnpj } from '@/shared/utils/document'

export const companyStepSchema = z.object({
  name: z.string().min(1, 'Informe o nome da empresa'),
  document: z
    .string()
    .min(1, 'Informe o CPF ou CNPJ')
    .refine(isValidCpfOrCnpj, 'Informe um CPF ou CNPJ válido'),
  phone: z.string().min(10, 'Informe um telefone válido'),
})

export type CompanyStepValues = z.infer<typeof companyStepSchema>

export const userStepSchema = z
  .object({
    name: z.string().min(1, 'Informe seu nome'),
    email: z.string().min(1, 'Informe seu e-mail').email('Informe um e-mail válido'),
    password: z
      .string()
      .min(8, 'Mínimo de 8 caracteres')
      .regex(/[A-Za-z]/, 'Inclua ao menos uma letra')
      .regex(/[0-9]/, 'Inclua ao menos um número'),
    password_confirmation: z.string().min(1, 'Confirme sua senha'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'As senhas não coincidem',
  })

export type UserStepValues = z.infer<typeof userStepSchema>

function luhnValid(value: string): boolean {
  const digits = value.replace(/\D/g, '')
  if (digits.length < 13 || digits.length > 19) return false

  let sum = 0
  let alternate = false

  for (let i = digits.length - 1; i >= 0; i -= 1) {
    let n = Number(digits[i])
    if (alternate) {
      n *= 2
      if (n > 9) n -= 9
    }
    sum += n
    alternate = !alternate
  }

  return sum % 10 === 0
}

function isExpirationValid(month: string, year: string): boolean {
  const m = Number(month)
  if (!Number.isInteger(m) || m < 1 || m > 12) return false

  const normalizedYear = year.length === 2 ? `20${year}` : year
  const y = Number(normalizedYear)
  if (!Number.isInteger(y) || normalizedYear.length !== 4) return false

  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = now.getMonth() + 1

  if (y < currentYear) return false
  if (y === currentYear && m < currentMonth) return false

  return true
}

/** Compatibilidade com fluxos que ainda enviam token client-side. */
export const creditCardTokenSchema = z.object({
  credit_card_token: z.string().min(1, 'Informe o token do cartão'),
  installments: z.coerce.number().int().min(1).max(12).optional(),
})

export type CreditCardTokenValues = z.infer<typeof creditCardTokenSchema>

export function toCreditCardTokenPaymentData(
  values: CreditCardTokenValues,
): Record<string, unknown> {
  return {
    credit_card_token: values.credit_card_token,
    ...(values.installments ? { installments: values.installments } : {}),
  }
}

/** @deprecated Use toCreditCardTokenPaymentData */
export const toCreditCardPaymentData = toCreditCardTokenPaymentData

export const creditCardSchema = z
  .object({
    holder_name: z
      .string()
      .min(1, 'Informe o nome impresso no cartão')
      .max(255, 'Nome muito longo'),
    number: z
      .string()
      .min(1, 'Informe o número do cartão')
      .refine((v) => luhnValid(v), 'Informe um número de cartão válido'),
    expiration_month: z
      .string()
      .min(1, 'Informe o mês')
      .regex(/^(0?[1-9]|1[0-2])$/, 'Mês inválido'),
    expiration_year: z
      .string()
      .min(1, 'Informe o ano')
      .regex(/^(\d{2}|\d{4})$/, 'Ano inválido'),
    cvv: z
      .string()
      .min(1, 'Informe o CVV')
      .regex(/^\d{3,4}$/, 'CVV inválido'),
    postal_code: z
      .string()
      .min(1, 'Informe o CEP')
      .refine((v) => v.replace(/\D/g, '').length === 8, 'CEP inválido'),
    address_number: z.string().min(1, 'Informe o número do endereço').max(20),
    address_complement: z.string().max(100).optional(),
    installments: z.coerce.number().int().min(1).max(12).optional(),
    recurring: z.boolean().default(false),
  })
  .superRefine((data, ctx) => {
    if (!isExpirationValid(data.expiration_month, data.expiration_year)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['expiration_month'],
        message: 'Data de validade expirada ou inválida',
      })
    }
  })

export type CreditCardValues = z.infer<typeof creditCardSchema>

export function toCreditCardPaymentDataFromCard(
  values: CreditCardValues,
): Record<string, unknown> {
  const year =
    values.expiration_year.length === 2
      ? `20${values.expiration_year}`
      : values.expiration_year

  return {
    credit_card: {
      holder_name: values.holder_name.trim(),
      number: values.number.replace(/\D/g, ''),
      expiration_month: values.expiration_month.padStart(2, '0'),
      expiration_year: year,
      cvv: values.cvv,
      postal_code: values.postal_code.replace(/\D/g, ''),
      address_number: values.address_number.trim(),
      ...(values.address_complement?.trim()
        ? { address_complement: values.address_complement.trim() }
        : {}),
    },
    ...(values.installments && values.installments > 1
      ? { installments: values.installments }
      : {}),
    recurring: Boolean(values.recurring),
  }
}

export const PASSWORD_REQUIREMENTS = [
  { id: 'length', label: 'Pelo menos 8 caracteres', test: (v: string) => v.length >= 8 },
  { id: 'letter', label: 'Pelo menos uma letra', test: (v: string) => /[A-Za-z]/.test(v) },
  { id: 'number', label: 'Pelo menos um número', test: (v: string) => /[0-9]/.test(v) },
] as const
