import { z } from 'zod'

export const chargeBillingSchema = z
  .object({
    payment_method: z.enum(['pix', 'boleto', 'credit_card']),
    holderName: z.string(),
    number: z.string(),
    expiryMonth: z.string(),
    expiryYear: z.string(),
    ccv: z.string(),
  })
  .superRefine((values, ctx) => {
    if (values.payment_method !== 'credit_card') return
    if (!values.holderName.trim()) {
      ctx.addIssue({ code: 'custom', path: ['holderName'], message: 'Informe o nome no cartão' })
    }
    if (!values.number.trim()) {
      ctx.addIssue({ code: 'custom', path: ['number'], message: 'Informe o número do cartão' })
    }
    if (!values.expiryMonth.trim()) {
      ctx.addIssue({ code: 'custom', path: ['expiryMonth'], message: 'Informe o mês' })
    }
    if (!values.expiryYear.trim()) {
      ctx.addIssue({ code: 'custom', path: ['expiryYear'], message: 'Informe o ano' })
    }
    if (!values.ccv.trim()) {
      ctx.addIssue({ code: 'custom', path: ['ccv'], message: 'Informe o CVV' })
    }
  })

export type ChargeBillingFormValues = z.infer<typeof chargeBillingSchema>

export const markPaidSchema = z.object({
  paid_amount: z.string(),
})

export type MarkPaidFormValues = z.infer<typeof markPaidSchema>
